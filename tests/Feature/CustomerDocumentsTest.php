<?php

use App\Livewire\Admin\CommercialDocuments;
use App\Livewire\Admin\ProjectDossier;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Quote;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CommercialPdf;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Features;
use Livewire\Livewire;

function portalProject(?User $user = null): ClientProject
{
    return ClientProject::create(['client_id' => Client::create(['name' => 'Client original', 'email' => 'contact@example.test'])->id, 'user_id' => $user?->id, 'name' => 'Projet privé', 'type' => 'Site vitrine', 'status' => 'development', 'internal_notes' => 'SECRET INTERNE']);
}

function portalDocument(ClientProject $project, array $attributes = []): Document
{
    Storage::disk('local')->put('portal/test.pdf', '%PDF-1.4 private');

    return Document::create(array_replace(['client_id' => $project->client_id, 'client_project_id' => $project->id, 'name' => 'Contrat du projet', 'type' => 'contract', 'document_date' => today(), 'path' => 'portal/test.pdf', 'mime' => 'application/pdf', 'notes' => 'NOTE CONFIDENTIELLE'], $attributes));
}

beforeEach(function () {
    Storage::fake('local');
});

test('customers only list and download explicitly shared project files', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = portalProject($owner);
    $visible = portalDocument($project, ['client_visible' => true]);
    $internal = portalDocument($project, ['name' => 'Interne invisible']);
    $archived = portalDocument($project, ['name' => 'Pièce archivée', 'client_visible' => true, 'archived_at' => now()]);
    $foreign = portalDocument(portalProject($other), ['name' => 'Autre client', 'client_visible' => true]);
    $this->actingAs($owner)->get('/espace-client')->assertOk()->assertSee('Mes projets et documents')->assertSee($visible->name)
        ->assertDontSee('SECRET INTERNE')->assertDontSee('NOTE CONFIDENTIELLE')->assertDontSee($internal->name)->assertDontSee($archived->name)->assertDontSee($foreign->name);
    $this->get(route('customer.documents.download', $visible))->assertOk()->assertDownload('Contrat du projet.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
    foreach ([$internal, $archived, $foreign] as $document) {
        $this->get(route('customer.documents.download', $document))->assertNotFound();
    }
    $this->get(route('admin.documents.download', $visible))->assertForbidden();
    $this->actingAs($other)->get(route('customer.documents.download', $visible))->assertNotFound();
    $this->post('/logout');
    $this->get(route('customer.documents.download', $visible))->assertRedirect('/login');
});

test('admin can attach an account after project creation and explicitly share then revoke a document', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create();
    $project = portalProject();
    $file = portalDocument($project);
    $this->actingAs($admin);
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project]);
    $component->call('toggleCustomerVisibility', $file->id)->assertHasErrors('sharing');
    $component->set('accountEmail', $owner->email)->call('saveAccess')->assertHasNoErrors()
        ->call('toggleCustomerVisibility', $file->id)->assertHasNoErrors();
    expect($project->refresh()->user_id)->toBe($owner->id)->and($file->refresh()->client_visible)->toBeTrue();
    $this->actingAs($owner)->get(route('customer.documents.download', $file))->assertOk();
    $this->actingAs($admin);
    Livewire::test(CommercialDocuments::class)->call('toggleCustomerVisibility', $file->id)->assertHasNoErrors();
    $this->actingAs($owner)->get(route('customer.documents.download', $file))->assertNotFound();
    $this->actingAs($admin);
    Livewire::test(ProjectDossier::class, ['clientProject' => $project])->set('accountEmail', '')->call('saveAccess')->assertHasNoErrors();
    expect($project->refresh()->user_id)->toBeNull();
});

test('reassigning a client revokes old access and preserves historic documents until explicitly reshared', function () {
    $owner = User::factory()->create();
    $replacement = User::factory()->create();
    $project = portalProject($owner);
    $file = portalDocument($project, ['client_visible' => true, 'snapshot' => ['client' => ['name' => 'Client original']]]);
    $previousClient = $project->client_id;
    $client = Client::create(['name' => 'Nouveau client', 'email' => $replacement->email]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project])
        ->set('accessClientId', (string) $client->id)->assertSet('accountEmail', '')
        ->set('accountEmail', $replacement->email)->call('saveAccess')->assertHasNoErrors();
    expect($project->refresh()->client_id)->toBe($client->id)->and($project->user_id)->toBe($replacement->id)
        ->and($file->refresh()->client_visible)->toBeFalse()->and($file->client_id)->toBe($previousClient)
        ->and($file->snapshot['client']['name'])->toBe('Client original');
    $admin = auth()->user();
    $this->actingAs($owner)->get(route('customer.documents.download', $file))->assertNotFound();
    $this->actingAs($replacement)->get(route('customer.documents.download', $file))->assertNotFound();
    $this->actingAs($admin);
    $component->call('toggleCustomerVisibility', $file->id)->assertHasNoErrors();
    $this->actingAs($replacement)->get(route('customer.documents.download', $file))->assertOk();
    $this->actingAs($owner)->get('/espace-client')->assertDontSee('Projet privé');
});

test('invalid account and foreign project actions cannot change customer access', function () {
    $project = portalProject();
    $otherFile = portalDocument(portalProject());
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project]);
    foreach (['missing@example.test', $admin->email] as $email) {
        $component->set('accountEmail', $email)->call('saveAccess')->assertHasErrors('accountEmail');
    }
    expect($project->refresh()->user_id)->toBeNull();
    $component->call('toggleCustomerVisibility', $otherFile->id)->assertNotFound();
    $this->actingAs(User::factory()->create());
    Livewire::test(ProjectDossier::class, ['clientProject' => $project])->assertForbidden();
    Livewire::test(CommercialDocuments::class)->assertForbidden();
});

test('draft billing PDFs cannot be shared or downloaded even with a forged visibility flag', function () {
    $owner = User::factory()->create();
    $project = portalProject($owner);
    $quote = Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today(), 'items' => [], 'totals' => [], 'snapshot' => []]);
    $file = portalDocument($project, ['quote_id' => $quote->id, 'type' => 'quote']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(CommercialDocuments::class)->call('toggleCustomerVisibility', $file->id)->assertHasErrors('sharing');
    $file->update(['client_visible' => true]);
    $this->actingAs($owner)->get(route('customer.documents.download', $file))->assertNotFound();
    $this->get('/espace-client')->assertDontSee($file->name);
});

test('customer file downloads require email verification when enabled', function () {
    config(['fortify.features' => [Features::emailVerification()]]);
    $owner = User::factory()->unverified()->create();
    $file = portalDocument(portalProject($owner), ['client_visible' => true]);
    $this->actingAs($owner)->getJson(route('customer.documents.download', $file))->assertForbidden();
});

test('quotes display clean quantities and the exact deposit acceptance condition only when applicable', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $project = portalProject();
    $items = [['name' => 'Site', 'description' => '', 'unit' => 'forfait', 'quantity_units' => 100, 'price_cents' => 100000, 'discount_value' => 0, 'discount_type' => 'percent', 'vat_basis' => 2000, 'frequency' => 'once', 'optional' => false, 'selected' => true]];
    $calculator = app(CommercialCalculator::class);
    $quote = Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today()->addDays(30), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 0, 'percent', 4000), 'snapshot' => app(CommercialBilling::class)->snapshot($project)]);
    $html = view('commercial.billing-pdf', ['record' => $quote, 'kind' => 'quote'])->render();
    expect($html)->not->toContain('forfait')->toContain('<td>1</td>')->toContain('480,00 €')->toContain('En signant ce devis')->toContain('avant le démarrage du projet');
    $pdf = app(CommercialPdf::class)->billing($quote);
    expect(substr(Storage::disk('local')->get($pdf->path), 0, 5))->toBe('%PDF-');
    $quote->update(['totals' => $calculator->calculate($items)]);
    expect(view('commercial.billing-pdf', ['record' => $quote, 'kind' => 'quote'])->render())->not->toContain('En signant ce devis');
});
