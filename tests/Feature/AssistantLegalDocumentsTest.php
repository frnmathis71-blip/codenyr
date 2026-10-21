<?php

use App\Livewire\Admin\ProjectDossier;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Services\AssistantTools;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\ProjectAutopilot;
use App\Services\ProjectTools;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** A project whose quote has been sent and accepted, ready for its start documents. */
function acceptedLegalProject(): ClientProject
{
    $client = Client::create(['name' => 'Menzel Kebab', 'email' => 'contact@menzel.test', 'audience' => 'professional', 'address' => '3 place de l’Hôtel de Ville, 71100 Chalon-sur-Saône', 'registration' => 'SIRET 123 456 789 00012']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Menzel Kebab', 'type' => 'Site vitrine', 'status' => 'quote_sent']);
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([['name' => 'Site vitrine', 'description' => 'Création du site', 'price' => '1500', 'quantity' => '1', 'discount' => '0', 'discount_type' => 'percent', 'vat' => '0', 'unit' => 'forfait', 'frequency' => 'once', 'optional' => false, 'selected' => true]], false);
    $quote = Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today()->addDays(30), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 0, 'percent', 4000), 'snapshot' => app(CommercialBilling::class)->snapshot($project), 'deposit_type' => 'percent', 'deposit_value' => 4000]);
    app(CommercialBilling::class)->finalize($quote);
    app(ProjectAutopilot::class)->answerQuote($quote, 'accepted');

    return $project->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();
    $this->actingAs($user);
});

test('the admin assistant drafts the CGV and contract for review, without making anything official', function () {
    $project = acceptedLegalProject();

    $result = app(AssistantTools::class)->run('prepare_legal_documents', ['project_id' => $project->id]);
    $data = json_decode($result['content'], true);

    expect($result['is_error'])->toBeFalse()->and($data['documents'])->toHaveCount(2);
    foreach ($data['documents'] as $draft) {
        expect($draft['official'])->toBeFalse()->and($draft['errors'])->toBe([])
            ->and($draft['references']['client']['name'])->toBe('Menzel Kebab')
            ->and($draft['references']['quote']['number'])->toBe(Quote::firstOrFail()->number)
            ->and($draft['text'])->toContain('Menzel Kebab');
        $document = Document::findOrFail($draft['id']);
        expect($document->finalized_at)->toBeNull()->and($document->path)->not->toBeNull()->and($document->snapshot['draft_content_hash'])->not->toBeNull();
    }
    // Drafting issues no invoice and keeps the project status.
    expect(Invoice::count())->toBe(0)->and($project->refresh()->status)->toBe('quote_accepted');

    // Preparing again updates the same drafts.
    app(AssistantTools::class)->run('prepare_legal_documents', ['project_id' => $project->id]);
    expect(Document::whereIn('type', ['terms', 'contract'])->count())->toBe(2);
});

test('the review is shown in the pilot, and validation turns the reviewed drafts into the official documents', function () {
    $project = acceptedLegalProject();
    $tools = app(AssistantTools::class);
    $tools->run('prepare_legal_documents', ['project_id' => $project->id]);
    $review = $tools->run('record_legal_review', ['project_id' => $project->id, 'summary' => 'Parties, devis et montants cohérents. Clauses B2B présentes.', 'points' => ['Confirmer le nom de domaine avec le client.']]);
    expect($review['is_error'])->toBeFalse()->and($project->notes()->value('content'))->toContain('Confirmer le nom de domaine');

    $drafts = Document::whereIn('type', ['terms', 'contract'])->pluck('id');
    $dossier = Livewire::test(ProjectDossier::class, ['clientProject' => $project])
        ->assertSee('Brouillons à relire')->assertSee('Relire le PDF')->assertSee('Parties, devis et montants cohérents')
        ->assertSee('Confirmer le nom de domaine avec le client.')->assertSee('Valider et créer la version officielle');

    $dossier->call('startKit')->assertHasNoErrors()->assertSet('kitMissing', []);
    $official = Document::whereIn('type', ['terms', 'contract'])->whereNull('original_id')->get();
    expect($official->pluck('id')->sort()->values()->all())->toBe($drafts->sort()->values()->all())
        ->and($official->every(fn (Document $document) => $document->finalized_at !== null))->toBeTrue()
        ->and(Invoice::where('invoice_type', 'deposit')->whereNotNull('finalized_at')->count())->toBe(1);

    // Once official, preparing again changes nothing.
    $again = json_decode($tools->run('prepare_legal_documents', ['project_id' => $project->id])['content'], true);
    expect(collect($again['documents'])->every(fn (array $document) => $document['official']))->toBeTrue();
});

test('a draft whose sources changed after the review cannot become official as is', function () {
    $project = acceptedLegalProject();
    app(AssistantTools::class)->run('prepare_legal_documents', ['project_id' => $project->id]);
    $project->client->update(['address' => '12 rue Nouvelle, 71100 Chalon-sur-Saône']);

    $result = app(ProjectAutopilot::class)->startKit($project->fresh());

    // The contract names the client's address; the CGV text did not change and is not concerned.
    expect(implode(' ', $result['missing']))->toContain('Contrat de prestation : le texte a changé depuis le brouillon relu')
        ->and(Document::whereIn('type', ['terms', 'contract'])->whereNotNull('finalized_at')->count())->toBe(0);

    // New drafts take the change into account and can then be validated.
    app(AssistantTools::class)->run('prepare_legal_documents', ['project_id' => $project->id]);
    expect(app(ProjectAutopilot::class)->startKit($project->fresh())['missing'])->toBe([])
        ->and(Document::where('type', 'contract')->whereNotNull('finalized_at')->sole()->content)->toContain('12 rue Nouvelle');
});

test('legal drafts need an accepted quote, and the agent can complete project details', function () {
    $client = Client::create(['name' => 'Atelier Rive', 'email' => 'contact@atelier-rive.test', 'audience' => 'professional']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Site Atelier Rive', 'type' => 'Site vitrine']);
    $tools = app(AssistantTools::class);

    expect($tools->run('prepare_legal_documents', ['project_id' => $project->id])['content'])->toContain('Le devis doit d’abord être accepté');

    $tools->run('update_project_details', ['project_id' => $project->id, 'domain' => 'atelier-rive.fr', 'host' => 'OVH', 'status' => 'completed']);
    expect($project->refresh()->domain)->toBe('atelier-rive.fr')->and($project->host)->toBe('OVH')
        // The status is not the admin assistant's to change.
        ->and($project->status)->toBe('quote_to_prepare');
    expect(collect(app(ProjectTools::class)->definitions())->firstWhere('name', 'delegate_to_admin')['description'])->toContain('CGV');
});
