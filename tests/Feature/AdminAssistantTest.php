<?php

use Anthropic\Client as AnthropicClient;
use App\Livewire\Admin\Assistant;
use App\Models\AdminTask;
use App\Models\AssistantConversation;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ProjectNote;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\AssistantTools;
use App\Services\CommercialBilling;
use App\Services\CommercialCatalog;
use App\Services\ProjectTools;
use App\Support\AssistantMarkup;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Plays scripted Messages API responses and keeps every request body. */
class FakeAnthropicTransport implements ClientInterface
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /** @param list<Closure(): array<string, mixed>> $turns Each returns the content blocks of one response. */
    public function __construct(private array $turns) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = json_decode((string) $request->getBody(), true);
        $content = (array_shift($this->turns) ?? fn () => [['type' => 'text', 'text' => 'Fin.']])();
        $toolUse = collect($content)->contains('type', 'tool_use');

        return new Response(200, ['content-type' => 'application/json'], (string) json_encode([
            'id' => 'msg_'.count($this->requests), 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => $content, 'stop_reason' => $toolUse ? 'tool_use' : 'end_turn', 'stop_sequence' => null, 'stop_details' => null,
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 200, 'cache_read_input_tokens' => 500, 'cache_creation_input_tokens' => 0],
        ]));
    }
}

function assistantAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

/** @param list<Closure(): array<string, mixed>> $turns */
function fakeClaude(array $turns): FakeAnthropicTransport
{
    config(['services.assistant.provider' => 'anthropic', 'services.anthropic.key' => 'test-key']);
    $transport = new FakeAnthropicTransport($turns);
    app()->instance(AnthropicClient::class, new AnthropicClient(apiKey: 'test-key', requestOptions: ['transporter' => $transport, 'maxRetries' => 0]));

    return $transport;
}

/** Plays the successive process() calls the page makes until the reply is complete. */
function finish(Testable $component): Testable
{
    for ($i = 0; $i < 40 && AssistantConversation::find($component->get('conversationId'))?->running(); $i++) {
        $component->call('process');
    }

    return $component;
}

function toolUse(string $id, string $name, array $input): array
{
    return ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => $input ?: new stdClass];
}

test('only admins can open the assistant', function () {
    $this->get('/admin/assistant')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/assistant')->assertForbidden();
    config(['services.assistant.provider' => 'anthropic', 'services.anthropic.key' => null]);
    $this->actingAs(assistantAdmin())->get('/admin/assistant')->assertOk()->assertSee('Assistant non configuré')->assertSee('ANTHROPIC_API_KEY');
    config(['services.assistant.provider' => 'gemini', 'services.gemini.key' => null]);
    $this->get('/admin/assistant')->assertSee('GEMINI_API_KEY');
    config(['services.gemini.key' => 'test-key']);
    $this->get('/admin/assistant')->assertDontSee('Assistant non configuré')->assertSee('Google peut réutiliser ces échanges');
});

test('the assistant also works with Gemini, replaying its turns unchanged', function () {
    $this->actingAs(assistantAdmin());
    config(['services.assistant.provider' => 'gemini', 'services.gemini.key' => 'gemini-test-key', 'services.gemini.model' => 'gemini-flash-latest']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['role' => 'model', 'parts' => [
            ['text' => 'Je crée la fiche.'],
            ['functionCall' => ['name' => 'create_client', 'args' => ['audience' => 'professional', 'name' => 'Boulangerie Martin', 'email' => 'julie@boulangerie-martin.test']], 'thoughtSignature' => 'sig-gemini'],
        ]]]], 'usageMetadata' => ['promptTokenCount' => 900, 'candidatesTokenCount' => 50, 'thoughtsTokenCount' => 30]])
        ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['role' => 'model', 'parts' => [['text' => 'Fiche créée.']]]]]]),
    ]);

    finish(Livewire::test(Assistant::class)->set('message', 'Nouveau client : Boulangerie Martin')->call('send'))
        ->assertSee('Je crée la fiche.')->assertSee('Fiche client créée : Boulangerie Martin')->assertSee('Fiche créée.');

    expect(Client::where('email', 'julie@boulangerie-martin.test')->exists())->toBeTrue();
    $conversation = AssistantConversation::firstOrFail();
    expect($conversation->provider)->toBe('gemini')->and($conversation->output_tokens)->toBe(80)->and($conversation->costDollars())->toBe(0.0);

    $requests = Http::recorded();
    [$second] = $requests[1];
    $contents = $second->data()['contents'];
    expect($second->header('x-goog-api-key'))->toBe(['gemini-test-key'])
        ->and($second->url())->toContain('models/gemini-flash-latest:generateContent')
        ->and($contents[1]['parts'][1]['thoughtSignature'])->toBe('sig-gemini')
        ->and($contents[2]['parts'][0]['functionResponse']['name'])->toBe('create_client')
        ->and($contents[2]['parts'][0]['functionResponse']['response'])->toHaveKey('client_id');
    $declarations = collect($second->data()['tools'][0]['functionDeclarations']);
    expect($declarations->firstWhere('name', 'create_quote')['parameters']['properties']['items']['items'])->not->toHaveKey('required');
});

test('an overloaded Gemini is retried, then explained', function () {
    $this->actingAs(assistantAdmin());
    config(['services.assistant.provider' => 'gemini', 'services.gemini.key' => 'gemini-test-key']);
    Sleep::fake();
    $busy = ['error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'This model is currently experiencing high demand.']];
    Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
        ->push($busy, 503)
        ->push(['candidates' => [['finishReason' => 'STOP', 'content' => ['role' => 'model', 'parts' => [['text' => 'Tout est à jour.']]]]]])
        ->push($busy, 503)->push($busy, 503)->push($busy, 503),
    ]);

    finish(Livewire::test(Assistant::class)->set('message', 'Fais le point')->call('send'))->assertSee('Tout est à jour.');
    finish(Livewire::test(Assistant::class)->set('message', 'Et maintenant ?')->call('send'))->assertSee('serveurs de Gemini sont surchargés');
    Http::assertSentCount(5);
});

test('an interrupted reply ends with a message instead of staying stuck', function () {
    $admin = assistantAdmin();
    $this->actingAs($admin);
    fakeClaude([fn () => [['type' => 'text', 'text' => 'Reprise.']]]);
    $stuck = AssistantConversation::create(['user_id' => $admin->id, 'agent' => 'admin', 'provider' => 'anthropic', 'status' => 'running', 'log' => [['role' => 'user', 'text' => 'Coiffeur Top Coiff']]]);
    AssistantConversation::whereKey($stuck->id)->update(['updated_at' => now()->subHours(4)]);

    Livewire::test(Assistant::class)->assertSee('L’assistant prépare le dossier')
        ->call('process')->assertSee('La réponse a été interrompue')->assertDontSee('L’assistant prépare le dossier');
    expect($stuck->refresh()->running())->toBeFalse();

    // A new message works again right away.
    finish(Livewire::test(Assistant::class)->set('message', 'Coiffeur Top Coiff, on reprend')->call('send'))->assertSee('Reprise.');
});

test('a Gemini quota error is explained to the admin', function () {
    $this->actingAs(assistantAdmin());
    config(['services.assistant.provider' => 'gemini', 'services.gemini.key' => 'gemini-test-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded', 'details' => [
        ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [['quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier', 'quotaValue' => '20']]],
        ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '41320s'],
    ]]], 429)]);

    finish(Livewire::test(Assistant::class)->set('message', 'Fais le point')->call('send'))
        ->assertSee('Quota gratuit du jour atteint pour ce modèle Gemini (20 requêtes')->assertSee('dans environ 11 h');
});

test('one message prepares the client, the project, a draft quote and a follow-up task', function () {
    $this->actingAs(assistantAdmin());
    app(CommercialCatalog::class)->initialize();
    $offer = Service::where('price_key', 'offer_vitrine')->firstOrFail();
    $extraPage = Service::where('catalog_key', 'extra_page')->firstOrFail();

    $transport = fakeClaude([
        fn () => [['type' => 'thinking', 'thinking' => '', 'signature' => 'sig-1'], toolUse('t1', 'find_clients', ['query' => 'Boulangerie Martin'])],
        fn () => [toolUse('t2', 'create_client', ['audience' => 'professional', 'name' => 'Boulangerie Martin', 'contact' => 'Julie Martin', 'email' => 'julie@boulangerie-martin.test'])],
        fn () => [toolUse('t3', 'create_project', ['client_id' => Client::where('email', 'julie@boulangerie-martin.test')->value('id'), 'name' => 'Site vitrine Boulangerie Martin', 'type' => 'Site vitrine', 'description' => 'Site de 7 pages avec formulaire de contact.'])],
        fn () => [toolUse('t4', 'create_quote', ['project_id' => ClientProject::where('name', 'Site vitrine Boulangerie Martin')->value('id'), 'items' => [['service_id' => $offer->id], ['service_id' => $extraPage->id, 'quantity' => 2]]])],
        fn () => [toolUse('t5', 'add_task', ['title' => 'Envoyer le devis à Boulangerie Martin', 'category' => 'Administratif'])],
        fn () => [['type' => 'text', 'text' => 'Dossier prêt : le devis est en brouillon, à relire.']],
    ]);

    $page = Livewire::test(Assistant::class)
        ->set('message', 'Nouveau client : Boulangerie Martin, pro, julie@boulangerie-martin.test, site vitrine 7 pages.')
        ->call('send')->assertSet('message', '')
        // The message shows at once; each process() then makes one model call, so no request runs long.
        ->assertSee('Nouveau client : Boulangerie Martin, pro')->assertSee('L’assistant prépare le dossier');
    $page->call('process');
    expect($transport->requests)->toHaveCount(1);
    finish($page)->assertSee('Dossier prêt')->assertSee('Fiche client créée : Boulangerie Martin')->assertSee('Devis préparé');

    $client = Client::where('email', 'julie@boulangerie-martin.test')->firstOrFail();
    $project = ClientProject::where('client_id', $client->id)->firstOrFail();
    $quote = Quote::where('client_project_id', $project->id)->firstOrFail();
    $expected = $offer->currentPrice() + 2 * $extraPage->currentPrice();

    expect($client->audience)->toBe('professional')
        ->and($project->status)->toBe('quote_to_prepare')
        ->and($quote->status)->toBe('draft')->and($quote->finalized_at)->toBeNull()->and($quote->number)->not->toBeNull()
        ->and($quote->totals['initial']['ht'])->toBe($expected)
        ->and($quote->items[1]['quantity_units'])->toBe(200)
        ->and(AdminTask::where('title', 'Envoyer le devis à Boulangerie Martin')->exists())->toBeTrue();

    // History is replayed unchanged: the thinking block and every tool result go back to the API.
    $last = end($transport->requests);
    expect($last['messages'][1]['content'][0])->toBe(['type' => 'thinking', 'signature' => 'sig-1', 'thinking' => ''])
        ->and($last['messages'][2]['content'][0]['tool_use_id'])->toBe('t1')
        ->and($last['fallbacks'])->toBe('default')
        ->and(collect($last['tools'])->pluck('name'))->not->toContain('finalize_quote');

    $conversation = AssistantConversation::firstOrFail();
    expect($conversation->input_tokens)->toBe(6000)->and($conversation->costDollars())->toBeGreaterThan(0);
});

test('invalid tool input is returned to the model as an error and creates nothing', function () {
    $this->actingAs(assistantAdmin());
    $transport = fakeClaude([
        fn () => [toolUse('t1', 'create_client', ['name' => 'Sans e-mail', 'audience' => 'consumer'])],
        fn () => [['type' => 'text', 'text' => 'Il me faut l’e-mail du client.']],
    ]);

    finish(Livewire::test(Assistant::class)->set('message', 'Nouveau client particulier : Paul Durand')->call('send'))->assertSee('Il me faut');

    $result = $transport->requests[1]['messages'][2]['content'][0];
    expect(Client::count())->toBe(0)->and($result['is_error'])->toBeTrue()->and($result['content'])->toContain('Refusé par l’application');
});

test('a sent quote cannot be changed by the assistant', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Atelier Rive', 'email' => 'contact@atelier-rive.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Atelier Rive', 'type' => 'Site vitrine']);
    $created = app(AssistantTools::class)->run('create_quote', ['project_id' => $project->id, 'items' => [['name' => 'Site vitrine', 'description' => 'Création du site', 'unit_price' => 1500]], 'deposit_percent' => 0]);
    $quote = Quote::firstOrFail();
    expect($created['is_error'])->toBeFalse()->and($quote->totals['initial']['ht'])->toBe(150000);

    // A draft can be revised…
    $revised = app(AssistantTools::class)->run('update_quote', ['quote_id' => $quote->id, 'items' => [['name' => 'Site vitrine', 'description' => 'Création du site', 'unit_price' => 1300.5]], 'deposit_percent' => 0]);
    expect($revised['is_error'])->toBeFalse()->and($quote->refresh()->totals['initial']['ht'])->toBe(130050);

    // …but not once it has been sent.
    app(CommercialBilling::class)->finalize($quote);
    $transport = fakeClaude([
        fn () => [toolUse('t1', 'update_quote', ['quote_id' => $quote->id, 'items' => [['name' => 'Site vitrine', 'unit_price' => 1000]]])],
        fn () => [['type' => 'text', 'text' => 'Ce devis est déjà envoyé.']],
    ]);
    finish(Livewire::test(Assistant::class)->set('message', 'Baisse le devis à 1 000 €')->call('send'));

    expect($transport->requests[1]['messages'][2]['content'][0]['is_error'])->toBeTrue()
        ->and($quote->refresh()->totals['initial']['ht'])->toBe(130050);
});

test('the project assistant reviews projects and keeps the dossier up to date', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Boulangerie Martin', 'email' => 'julie@boulangerie-martin.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Boulangerie Martin', 'type' => 'Site vitrine', 'status' => 'development', 'starts_on' => today(), 'due_on' => today()->addDays(2)]);
    ClientProject::create(['client_id' => $client->id, 'name' => 'Ancien site', 'type' => 'Refonte', 'status' => 'completed']);

    $transport = fakeClaude([
        fn () => [toolUse('t1', 'list_projects', [])],
        fn () => [toolUse('t2', 'get_project', ['project_id' => $project->id])],
        fn () => [
            toolUse('t3', 'update_project', ['project_id' => $project->id, 'status' => 'validation', 'due_on' => today()->addDays(30)->format('Y-m-d'), 'confirmed_outside_app' => true]),
            toolUse('t4', 'add_project_note', ['project_id' => $project->id, 'content' => 'Maquette terminée, validation demain.']),
            toolUse('t5', 'add_task', ['title' => 'Boulangerie Martin : relancer pour la validation', 'category' => 'Autre']),
        ],
        fn () => [['type' => 'text', 'text' => 'Projet passé en validation, livraison décalée.']],
    ]);

    finish(Livewire::test(Assistant::class, ['agent' => 'projects'])
        ->assertSee('Fais le point sur mes projets')
        ->set('message', 'J’ai fini la maquette de la boulangerie, on décale la livraison d’un mois.')->call('send'))
        ->assertSee('Projet passé en validation')->assertSee('Projet mis à jour : Site Boulangerie Martin');

    $listed = json_decode($transport->requests[1]['messages'][2]['content'][0]['content'], true);
    expect(collect($listed)->pluck('name')->all())->toBe(['Site Boulangerie Martin'])
        ->and($listed[0]['alerts'])->toContain('Devis pas encore créé')
        ->and($listed[0]['next_step'])->toBe('Devis préparé');

    // The three tool calls of one turn come back together, in a single user message.
    expect($transport->requests[3]['messages'][6]['content'])->toHaveCount(3)
        ->and(collect($transport->requests[3]['tools'])->pluck('name')->all())->toBe(['list_projects', 'get_project', 'update_project', 'add_project_note', 'add_task', 'complete_task', 'list_tasks', 'delegate_to_admin']);

    $project->refresh();
    expect($project->status)->toBe('validation')
        ->and($project->due_on->format('Y-m-d'))->toBe(today()->addDays(30)->format('Y-m-d'))
        ->and($project->notes()->value('content'))->toBe('Maquette terminée, validation demain.')
        ->and(AdminTask::where('title', 'like', 'Boulangerie Martin%')->exists())->toBeTrue()
        ->and(AssistantConversation::firstOrFail()->agent)->toBe('projects');
});

test('the project assistant refuses inconsistent dates and archived projects', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Atelier Rive', 'email' => 'contact@atelier-rive.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Atelier Rive', 'type' => 'Site vitrine', 'starts_on' => today()]);
    $tools = app(ProjectTools::class);

    expect($tools->run('update_project', ['project_id' => $project->id, 'due_on' => today()->subDay()->format('Y-m-d')])['is_error'])->toBeTrue();

    $done = $tools->run('update_project', ['project_id' => $project->id, 'status' => 'completed', 'confirmed_outside_app' => true]);
    expect($done['is_error'])->toBeFalse()->and($project->refresh()->status)->toBe('completed')
        ->and($project->events()->pluck('description'))->toContain('Site livré au client');

    $project->update(['archived_at' => now()]);
    expect($tools->run('update_project', ['project_id' => $project->id, 'status' => 'development'])['content'])->toContain('archivé');
});

test('the project assistant delegates paperwork to the admin assistant and gets its report', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Boulangerie Martin', 'email' => 'julie@boulangerie-martin.test', 'audience' => 'professional']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Boulangerie Martin', 'type' => 'Site vitrine', 'status' => 'development']);
    ProjectNote::create(['client_project_id' => $project->id, 'content' => 'Prix convenu au téléphone : 250 € pour la page boutique.']);

    // One transport serves both agents, in call order: project assistant, admin assistant, project assistant.
    $transport = fakeClaude([
        fn () => [toolUse('p1', 'delegate_to_admin', ['instructions' => 'Prépare un devis complémentaire : page boutique à 250 € HT.', 'project_id' => $project->id])],
        fn () => [toolUse('a1', 'create_quote', ['project_id' => $project->id, 'items' => [['name' => 'Page boutique', 'description' => 'Page boutique supplémentaire', 'unit_price' => 250]], 'deposit_percent' => 0])],
        fn () => [['type' => 'text', 'text' => 'Devis complémentaire préparé en brouillon.']],
        fn () => [['type' => 'text', 'text' => 'C’est fait : devis complémentaire de 250 € en brouillon.']],
    ]);

    $page = Livewire::test(Assistant::class, ['agent' => 'projects'])
        ->set('message', 'Le client veut une page boutique en plus, prépare le devis.')->call('send');
    // While the admin assistant works, the page says so.
    $page->call('process')->assertSee('L’assistant administratif s’occupe de la paperasse');
    finish($page)->assertSee('C’est fait')->assertSee('Assistant administratif — Devis préparé');

    expect(Quote::where('client_project_id', $project->id)->first()?->totals['initial']['ht'])->toBe(25000);

    // The admin assistant got the instructions with the project file, its own tools, and no way to call back.
    $adminRequest = $transport->requests[1];
    expect($adminRequest['messages'][0]['content'])->toContain('page boutique à 250 €')->toContain('Prix convenu au téléphone')
        ->and(collect($adminRequest['tools'])->pluck('name'))->toContain('create_quote')->not->toContain('delegate_to_admin');

    // Its report goes back to the project assistant as the tool result.
    $report = json_decode($transport->requests[3]['messages'][2]['content'][0]['content'], true);
    expect($report['report'])->toBe('Devis complémentaire préparé en brouillon.')->and($report['done'])->toHaveCount(1);

    // The delegated work stays reviewable in the admin assistant's history.
    $delegated = AssistantConversation::where('agent', 'admin')->firstOrFail();
    expect($delegated->title)->toStartWith('Demande du chef de projet')
        ->and($delegated->log[0]['text'])->not->toContain('Dossier actuel du projet');
});

test('a project status cannot get ahead of what the dossier shows', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Menzel Kebab', 'email' => 'contact@menzel.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Menzel Kebab', 'type' => 'Site vitrine', 'status' => 'quote_sent']);
    $tools = app(ProjectTools::class);

    // "The client accepted": not recorded in the pilot yet.
    $early = $tools->run('update_project', ['project_id' => $project->id, 'status' => 'waiting_deposit']);
    expect($early['is_error'])->toBeTrue()->and($early['content'])->toContain('« Devis accepté »')->toContain('Pilote du dossier');

    // "Everything is fine, we can start": signatures and deposit are missing.
    $start = $tools->run('update_project', ['project_id' => $project->id, 'status' => 'ready']);
    expect($start['content'])->toContain('« Signatures reçues »')->and($project->refresh()->status)->toBe('quote_sent');

    // Statuses before acceptance stay free, and an explicit confirmation lifts the check.
    expect($tools->run('update_project', ['project_id' => $project->id, 'status' => 'waiting_client'])['is_error'])->toBeFalse()
        ->and($tools->run('update_project', ['project_id' => $project->id, 'status' => 'ready', 'confirmed_outside_app' => true])['is_error'])->toBeFalse();

    // The dossier tells the assistant what really comes next.
    expect(json_decode($tools->run('get_project', ['project_id' => $project->id])['content'], true)['next_step'])->toBe('Devis préparé');
});

test('tasks belong to their project and get closed there', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Menzel Kebab', 'email' => 'contact@menzel.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Menzel Kebab', 'type' => 'Site vitrine']);
    $admin = app(AssistantTools::class);
    $chief = app(ProjectTools::class);

    $created = json_decode($admin->run('add_task', ['title' => 'Préciser le besoin de commande en ligne', 'category' => 'Autre', 'project_id' => $project->id])['content'], true);
    $task = AdminTask::findOrFail($created['task_id']);
    expect($task->client_project_id)->toBe($project->id);

    // Both agents see it with the project.
    $dossier = json_decode($chief->run('get_project', ['project_id' => $project->id])['content'], true);
    $search = json_decode($admin->run('find_clients', ['query' => 'Menzel'])['content'], true);
    $listed = collect(json_decode($chief->run('list_tasks', [])['content'], true))->firstWhere('id', $task->id);
    expect($dossier['open_tasks'][0]['id'])->toBe($task->id)
        ->and($search['clients'][0]['projects'][0]['open_tasks'][0]['id'])->toBe($task->id)
        ->and($listed['project']['name'])->toBe('Site Menzel Kebab');
    $this->get('/admin/a-faire')->assertSee('Projet : Site Menzel Kebab');

    // Either agent can close it once settled, and the dossier history keeps track.
    $admin->run('complete_task', ['task_id' => $task->id]);
    expect($task->refresh()->done_at)->not->toBeNull()
        ->and(json_decode($chief->run('get_project', ['project_id' => $project->id])['content'], true)['open_tasks'])->toBe([])
        ->and($project->events()->pluck('description'))->toContain('Tâche cochée par l’assistant : Préciser le besoin de commande en ligne');
});

test('the project assistant reads and completes to-do tasks', function () {
    $this->actingAs(assistantAdmin());
    $task = AdminTask::create(['category' => 'Autre', 'title' => 'Boulangerie Martin : envoyer la maquette', 'position' => 999]);
    $tools = app(ProjectTools::class);

    expect(collect(json_decode($tools->run('list_tasks', [])['content'], true))->pluck('title'))->toContain($task->title);
    $tools->run('complete_task', ['task_id' => $task->id]);
    expect($task->refresh()->done_at)->not->toBeNull()
        ->and(collect(json_decode($tools->run('list_tasks', [])['content'], true))->pluck('title'))->not->toContain($task->title);
});

test('replies link quote numbers, project and client names to their pages', function () {
    $this->actingAs(assistantAdmin());
    $client = Client::create(['name' => 'Menzel Kebab', 'email' => 'contact@menzel.test']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Vitrine Menzel Kebab', 'type' => 'Site vitrine']);
    app(AssistantTools::class)->run('create_quote', ['project_id' => $project->id, 'items' => [['name' => 'Site', 'description' => 'Site', 'unit_price' => 3020]], 'deposit_percent' => 0]);
    $quote = Quote::firstOrFail();

    $html = AssistantMarkup::render("L’assistant a préparé le devis **{$quote->number}** pour **Site Vitrine Menzel Kebab**. Menzel Kebab sera ravi. [piège](https://exemple.com/x) et [dossier](/admin/projets/{$project->id})");

    expect($html)->toContain('<strong><a href="'.route('admin.billing.quotes.edit', ['id' => $quote->id]).'">'.$quote->number.'</a></strong>')
        ->toContain('<a href="'.route('admin.client-projects.show', ['clientProject' => $project->id]).'">Site Vitrine Menzel Kebab</a>')
        // The client name alone points to its project too, without breaking the longer project link.
        ->toContain('. <a href="'.route('admin.client-projects.show', ['clientProject' => $project->id]).'">Menzel Kebab</a> sera ravi')
        // Only links inside the application are kept.
        ->not->toContain('exemple.com')->toContain('piège')
        ->toContain('href="/admin/projets/'.$project->id.'"');
});

test('the side panel follows the admin across pages with the current conversation', function () {
    $admin = assistantAdmin();
    $this->actingAs($admin);
    config(['services.assistant.provider' => 'anthropic', 'services.anthropic.key' => 'test-key']);
    $older = AssistantConversation::create(['user_id' => $admin->id, 'agent' => 'projects', 'title' => 'Point du lundi', 'log' => [['role' => 'assistant', 'text' => 'Ancienne réponse']]]);
    $current = AssistantConversation::create(['user_id' => $admin->id, 'agent' => 'projects', 'title' => 'Menzel Kebab', 'log' => [['role' => 'assistant', 'text' => 'Le devis est prêt.']]]);
    AssistantConversation::whereKey($current->id)->update(['updated_at' => now()->subDay()]);

    // Opening a conversation on the full page makes it the panel's.
    Livewire::test(Assistant::class, ['agent' => 'projects'])->call('open', $current->id);
    $this->get('/admin/a-faire')->assertOk()->assertSee('assistant-drawer')->assertSee('Le devis est prêt.')->assertDontSee('Ancienne réponse');

    // The panel can switch agent and start afresh; the full page has no duplicate panel.
    Livewire::test(Assistant::class, ['panel' => true])->assertSet('agent', 'projects')->assertSet('conversationId', $current->id)
        ->call('switchAgent', 'admin')->assertSet('agent', 'admin')->assertSet('conversationId', null);
    $this->get('/admin/assistant')->assertOk()->assertDontSee('assistant-drawer');
    Livewire::test(Assistant::class)->call('switchAgent', 'projects')->assertNotFound();
});

test('each assistant keeps its own conversations', function () {
    $admin = assistantAdmin();
    AssistantConversation::create(['user_id' => $admin->id, 'agent' => 'admin', 'title' => 'Dossier boulangerie']);
    $this->actingAs($admin);
    config(['services.anthropic.key' => 'test-key']);

    Livewire::test(Assistant::class, ['agent' => 'projects'])->assertDontSee('Dossier boulangerie');
    Livewire::test(Assistant::class)->assertSee('Dossier boulangerie');
    $this->get('/admin/assistant/inconnu')->assertNotFound();
});

test('the admin only sees their own conversations', function () {
    $other = AssistantConversation::create(['user_id' => assistantAdmin()->id, 'title' => 'Dossier privé']);
    $this->actingAs(assistantAdmin());
    config(['services.anthropic.key' => 'test-key']);

    Livewire::test(Assistant::class)->assertDontSee('Dossier privé')->call('open', $other->id)->assertNotFound();
});
