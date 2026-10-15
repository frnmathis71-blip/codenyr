<?php

use App\Livewire\Admin\ProjectDossier;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\ProjectAutopilot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function autopilotAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

function autopilotProject(): ClientProject
{
    $client = Client::create(['name' => 'Restaurant Sithinem', 'email' => 'contact@sithinem.test', 'address' => '1 rue du Port, 71100 Chalon-sur-Saône', 'registration' => 'SIRET 123 456 789 00012']);

    return ClientProject::create(['client_id' => $client->id, 'name' => 'Site Restaurant Sithinem', 'type' => 'Site vitrine', 'status' => 'quote_sent']);
}

function autopilotQuote(ClientProject $project): Quote
{
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([['name' => 'Site vitrine', 'description' => 'Création du site', 'price' => '1500', 'quantity' => '1', 'discount' => '0', 'discount_type' => 'percent', 'vat' => '0', 'unit' => 'forfait', 'frequency' => 'once', 'optional' => false, 'selected' => true]], false);

    return Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today()->addDays(30), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 0, 'percent', 4000), 'snapshot' => app(CommercialBilling::class)->snapshot($project), 'deposit_type' => 'percent', 'deposit_value' => 4000]);
}

test('the autopilot takes a project from sent quote to paid balance with one click per step', function () {
    Storage::fake('local');
    $this->actingAs(autopilotAdmin());
    $project = autopilotProject();
    $quote = autopilotQuote($project);
    app(CommercialBilling::class)->finalize($quote);
    $pilot = app(ProjectAutopilot::class);

    expect($pilot->current($project->fresh()))->toBe('accepted');
    $dossier = Livewire::test(ProjectDossier::class, ['clientProject' => $project])->assertSee('Pilote du dossier')->assertSee('Le client a accepté');

    $dossier->call('answerQuote', $quote->id, 'accepted')->assertHasNoErrors();
    expect($quote->refresh()->status)->toBe('accepted')->and($pilot->current($project->fresh()))->toBe('kit');

    // The only question asked before generating everything.
    $dossier->assertSee('Un professionnel')->call('startKit')->assertHasErrors('autopilot');
    $dossier->call('setAudience', 'professional')->call('startKit')->assertHasNoErrors()->assertSet('kitMissing', []);

    $project->refresh()->load('client');
    expect($project->client->country)->toBe('France')
        ->and($project->client->signatory)->toBe('Restaurant Sithinem')
        ->and($project->starts_on)->not->toBeNull()
        ->and($project->due_on)->not->toBeNull();
    foreach (['terms', 'contract'] as $type) {
        $document = Document::where('client_project_id', $project->id)->where('type', $type)->whereNull('original_id')->sole();
        expect($document->finalized_at)->not->toBeNull()->and($document->path)->not->toBeNull()
            ->and($document->content)->not->toContain('[À compléter');
    }
    $deposit = Invoice::where('client_project_id', $project->id)->where('invoice_type', 'deposit')->sole();
    expect($deposit->finalized_at)->not->toBeNull()->and($deposit->number)->not->toBeNull();
    expect($pilot->current($project->fresh()))->toBe('signed');

    // Signed copies are imported through the documents module; simulated here.
    foreach (Document::where('client_project_id', $project->id)->whereIn('type', ['terms', 'contract'])->whereNull('original_id')->get() as $original) {
        Document::create(['name' => $original->name.' — signé', 'type' => $original->type, 'client_id' => $original->client_id, 'client_project_id' => $project->id, 'original_id' => $original->id, 'document_date' => today(), 'status' => 'signed', 'path' => UploadedFile::fake()->create('signe.pdf', 10, 'application/pdf')->store('signed', 'local'), 'mime' => 'application/pdf']);
    }
    expect($pilot->current($project->fresh()))->toBe('deposit');

    $dossier->call('markPaid', $deposit->id)->assertHasNoErrors();
    expect($deposit->refresh()->remainingCents())->toBe(0)->and($pilot->current($project->fresh()))->toBe('delivered');

    $dossier->call('markDelivered')->call('finalInvoice')->assertHasNoErrors();
    $balance = Invoice::where('client_project_id', $project->id)->where('invoice_type', 'balance')->sole();
    expect($balance->finalized_at)->not->toBeNull()
        ->and($deposit->totals['initial']['ttc'] + $balance->totals['initial']['ttc'])->toBe($quote->totals['initial']['ttc']);

    $dossier->call('markPaid', $balance->id)->assertHasNoErrors();
    expect($pilot->current($project->fresh()))->toBeNull()->and(Payment::count())->toBe(2);
    $dossier->assertSee('Dossier complet');
});

test('the start kit never regenerates finalized documents nor issues a second deposit', function () {
    Storage::fake('local');
    $this->actingAs(autopilotAdmin());
    $project = autopilotProject();
    $project->client->update(['audience' => 'consumer']);
    $quote = autopilotQuote($project);
    app(CommercialBilling::class)->finalize($quote);
    app(ProjectAutopilot::class)->answerQuote($quote, 'accepted');

    expect(app(ProjectAutopilot::class)->startKit($project->fresh())['missing'])->toBe([]);
    app(ProjectAutopilot::class)->startKit($project->fresh());

    expect(Document::where('client_project_id', $project->id)->whereIn('type', ['terms', 'contract'])->count())->toBe(2)
        ->and(Invoice::where('client_project_id', $project->id)->where('invoice_type', 'deposit')->count())->toBe(1);
    // Consumer documents carry the withdrawal right and mediation.
    expect(Document::where('client_project_id', $project->id)->where('type', 'terms')->sole()->content)->toContain('rétractation');
});

test('standard profile fills legal identity and mandatory invoice mentions without overriding saved settings', function () {
    $settings = CommercialSetting::current();
    expect($settings['legal_name'])->toBe('Mathis Fornari (EI)')
        ->and($settings['tax_mention'])->toBe('TVA non applicable, art. 293 B du CGI')
        ->and($settings['invoice_mention'])->toContain('40 €');

    CommercialSetting::updateOrCreate(['id' => 1], ['values' => ['legal_name' => 'Autre nom', 'invoice_mention' => '']]);
    $settings = CommercialSetting::current();
    expect($settings['legal_name'])->toBe('Autre nom')->and($settings['invoice_mention'])->toContain('40 €');
});
