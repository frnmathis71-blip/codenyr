<?php

use App\Livewire\Admin\CommercialDirectory;
use App\Livewire\Admin\CommercialDocuments;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Quote;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CommercialPdf;
use App\Services\DocumentVariables;
use App\Services\LegalDocumentRules;
use App\Services\ProjectLegalDocuments;
use App\Services\TermsVersions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function completeLegalDocument(string $type = 'contract'): Document
{
    $settings = CommercialSetting::current();
    foreach (['legal_name', 'legal_status', 'address', 'registration', 'tax_mention', 'signatory', 'signatory_role'] as $key) {
        $settings[$key] = 'Entreprise test '.$key;
    }
    foreach (LegalDocumentRules::CLAUSES as $key => $label) {
        $settings['clause_'.$key] = 'Clause configurée : '.$label;
    }
    CommercialSetting::updateOrCreate(['id' => 1], ['values' => $settings]);
    $client = Client::create(['name' => 'Client test', 'email' => 'client@example.test', 'address' => '1 rue du Test', 'country' => 'France', 'audience' => 'professional', 'registration' => '123456789', 'signatory' => 'Camille Test', 'signatory_role' => 'Direction']);
    $project = ClientProject::create(['client_id' => $client->id, 'name' => 'Projet test', 'type' => 'Site vitrine', 'status' => 'development', 'description' => 'Présentation du projet', 'starts_on' => today(), 'due_on' => today()->addMonth()]);
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([['name' => 'Prestation originale', 'description' => 'Fonction prévue explicitement', 'price' => '1000', 'quantity' => '1', 'discount' => '0', 'discount_type' => 'percent', 'vat' => '0', 'unit' => 'forfait', 'frequency' => 'once', 'optional' => false, 'selected' => true]], false);
    $quote = Quote::create(['client_project_id' => $project->id, 'number' => 'DEV-TEST-'.$project->id, 'issued_on' => today(), 'due_on' => today()->addMonth(), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 0, 'percent', 2500), 'snapshot' => app(CommercialBilling::class)->snapshot($project), 'deposit_type' => 'percent', 'deposit_value' => 2500, 'conditions' => '25 % à la commande et 75 % à la livraison', 'estimated_delay' => 'Un mois']);
    app(CommercialBilling::class)->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $template = DocumentTemplate::create(['name' => 'CGV Codenyr', 'type' => 'terms', 'content' => 'Texte général applicable de {{seller}}.', 'effective_on' => today()->format('Y-m-d')]);
    $fields = [];
    foreach (LegalDocumentRules::sections() as $section) {
        foreach ($section as $key => $label) {
            $fields[$key] = 'Précision : '.$label;
        }
    }
    $fields = array_replace($fields, ['terms_template_id' => (string) $template->id, 'review_days' => '10', 'revision_rounds' => '0', 'show_technologies' => 'non', 'ip_mode' => 'cession', 'domain_mode' => 'non_applicable', 'hosting_mode' => 'non_applicable', 'maintenance_mode' => 'aucune', 'privacy_processing' => 'non', 'additional' => '', 'privacy_annex_id' => '', 'annex_state' => '']);

    return Document::create(['name' => 'Document test', 'type' => $type, 'client_id' => $client->id, 'client_project_id' => $project->id, 'quote_id' => $quote->id, 'document_date' => today(), 'contract_data' => $fields, 'content' => 'Brouillon', 'status' => 'draft']);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Storage::fake('local');
});

test('legal finalization freezes sources original bytes hash logo and author and is idempotent', function () {
    $document = completeLegalDocument();
    $result = app(ProjectLegalDocuments::class)->prepare($document);
    expect($result['errors'])->toBe([])->and($result['percent'])->toBe(100)->and($result['content'])->toContain('250,00 €', '750,00 €', '25 %');
    app(CommercialPdf::class)->document($document);
    $document->refresh();
    $bytes = Storage::disk('local')->get($document->path);
    $snapshot = $document->snapshot;
    expect($document->finalized_at)->not->toBeNull()->and($document->finalized_by)->toBe(auth()->id())->and($document->pdf_sha256)->toBe(hash('sha256', $bytes));
    $document->client->update(['name' => 'Nom modifié']);
    CommercialSetting::find(1)->update(['values' => ['name' => 'Autre entreprise']]);
    app(CommercialPdf::class)->document($document);
    expect($document->refresh()->snapshot)->toBe($snapshot)->and($document->versions()->count())->toBe(1)->and(Storage::disk('local')->get($document->path))->toBe($bytes);
    expect(fn () => $document->update(['content' => 'Altération']))->toThrow(LogicException::class);
    $this->get(route('admin.documents.download', $document))->assertOk();
});

test('terms are general without detailed quote prices technologies or planning', function () {
    $document = completeLegalDocument('terms');
    $result = app(ProjectLegalDocuments::class)->prepare($document);
    expect($result['errors'])->toBe([])->and($result['content'])->toContain('ARTICLE 21', 'DEV-TEST-1')->not->toContain('Prestation originale', 'Fonction prévue explicitement', '250,00', '750,00', 'Début :');
});

test('unknown audience incomplete rights and residual markers block server finalization', function (string $mutation, string $key) {
    $document = completeLegalDocument();
    if ($mutation === 'audience') {
        $document->client->update(['audience' => null]);
    } else {
        $document->update(['contract_data' => array_replace($document->contract_data, [$mutation => $mutation === 'ip_territory' ? '' : 'TODO {{unresolved}}'])]);
    }
    $result = app(ProjectLegalDocuments::class)->prepare($document->fresh());
    expect($result['errors'])->toHaveKey($key);
    expect(fn () => app(CommercialPdf::class)->document($document))->toThrow(ValidationException::class);
    expect($document->refresh()->finalized_at)->toBeNull()->and($document->path)->toBeNull();
})->with([['audience', 'audience'], ['ip_territory', 'ip_territory'], ['additional', 'content']]);

test('incomplete drafts generate marked PDFs without gaining finalization or sharing rights', function () {
    $document = completeLegalDocument();
    $document->client->update(['audience' => null]);
    app(CommercialPdf::class)->document($document, false);
    expect($document->refresh()->finalized_at)->toBeNull()->and($document->status)->toBe('draft');
    expect(view('commercial.document-pdf', ['document' => $document])->render())->toContain('BROUILLON — DOCUMENT NON FINALISÉ');
    expect(Document::customerReady()->whereKey($document->id)->exists())->toBeFalse();
    $document->client->update(['audience' => 'professional']);
    app(CommercialPdf::class)->document($document);
    expect($document->refresh()->finalized_at)->not->toBeNull()->and($document->versions()->count())->toBe(2);
});

test('PDF storage failure cannot leave a partially finalized document', function () {
    $document = completeLegalDocument();
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('put')->andReturn(false);
    Storage::shouldReceive('delete')->andReturn(true);
    expect(fn () => app(CommercialPdf::class)->document($document))->toThrow(RuntimeException::class);
    expect($document->refresh()->finalized_at)->toBeNull()->and($document->path)->toBeNull()->and($document->versions()->count())->toBe(0);
});

test('unreadable logo blocks PDF finalization without changing the document', function () {
    $document = completeLegalDocument();
    $settings = CommercialSetting::current();
    $settings['logo_path'] = 'commercial/logos/unreadable.png';
    CommercialSetting::findOrFail(1)->update(['values' => $settings]);
    Storage::shouldReceive('disk')->with('local')->andReturnSelf();
    Storage::shouldReceive('exists')->with($settings['logo_path'])->andReturn(true);
    Storage::shouldReceive('get')->with($settings['logo_path'])->andReturn(null);

    expect(fn () => app(CommercialPdf::class)->document($document))
        ->toThrow(RuntimeException::class, 'Le logo du document ne peut pas être lu.');
    expect($document->refresh()->finalized_at)->toBeNull()
        ->and($document->path)->toBeNull()
        ->and($document->versions()->count())->toBe(0);
});

test('new terms versions preserve previous text date and configured clauses', function () {
    $document = completeLegalDocument('terms');
    $template = DocumentTemplate::findOrFail($document->contract_data['terms_template_id']);
    $old = $template->toArray();
    Livewire::test(CommercialDirectory::class, ['module' => 'templates'])->call('edit', $template->id)->set('form.content', 'Nouvelle version')->call('save')->assertHasNoErrors();
    expect($template->refresh()->toArray())->toBe($old);
    $next = DocumentTemplate::latest('id')->first();
    expect($next->previous_id)->toBe($template->id)->and($next->revision)->toBe(2);
    expect(fn () => $template->update(['content' => 'Altération']))->toThrow(LogicException::class);
    expect(app(ProjectLegalDocuments::class)->prepare($document)['content'])->toContain('Texte général applicable')->not->toContain('Nouvelle version');
});

test('source priority preserves explicit zero no maintenance and personal text on repeated prefill', function () {
    $document = completeLegalDocument();
    $project = $document->project;
    $project->update(['contract_data' => ['revision_rounds' => '0', 'privacy_processing' => 'non']]);
    $component = Livewire::test(CommercialDocuments::class)->call('revise', $document->id)->set('contract.object', 'Objet reformulé')->set('contract.additional', 'Précision personnalisée')->call('prepareLegal')->call('prepareLegal')->assertHasNoErrors()->assertSet('contract.object', 'Objet reformulé')->assertSet('contract.additional', 'Précision personnalisée');
    $result = app(ProjectLegalDocuments::class)->prepare($document->fresh());
    expect($result['fields']['revision_rounds'])->toBe('0')->and($result['provenance']['revision_rounds'])->toBe('Projet');
    $component->set('form.quote_id', '')->assertSet('form.invoice_id', '')->assertDontSee('250,00 €');
});

test('foreign relations and missing mandatory annexes block finalization', function () {
    $document = completeLegalDocument();
    $other = completeLegalDocument();
    $document->update(['quote_id' => $other->quote_id, 'contract_data' => [...$document->contract_data, 'privacy_processing' => 'oui', 'privacy_annex_id' => (string) $other->id]]);
    $result = app(ProjectLegalDocuments::class)->prepare($document->fresh());
    expect($result['errors'])->toHaveKeys(['quote_relation', 'privacy_annex_id']);
});

test('review detects business changes and signed import preserves original PDF', function () {
    $document = completeLegalDocument();
    $component = Livewire::test(CommercialDocuments::class)->call('review', $document->id)->assertHasNoErrors();
    $document->client->update(['address' => 'Nouvelle adresse']);
    $component->call('generate', $document->id)->assertHasErrors('document');
    $component->call('review', $document->id)->call('generate', $document->id)->assertHasNoErrors();
    $path = $document->refresh()->path;
    Livewire::test(CommercialDocuments::class)->call('sign', $document->id)->set('upload', UploadedFile::fake()->createWithContent('signe.pdf', Storage::disk('local')->get($path)))->call('save')->assertHasNoErrors();
    $signed = $document->signedCopies()->firstOrFail();
    expect($document->refresh()->path)->toBe($path)->and($signed->path)->not->toBe($path)->and($signed->signed_imported_at)->not->toBeNull()->and($signed->signed_imported_by)->toBe(auth()->id());
});

test('completed legal form generates its PDF in one submission', function (string $type) {
    $original = completeLegalDocument($type);
    $clientName = $original->client->name;
    $component = Livewire::test(CommercialDocuments::class)
        ->call('revise', $original->id)
        ->assertSee('Remplir le document')
        ->assertSeeHtml('wire:submit="fillDocument"')
        ->set('contract.client_name', 'Client renseigné dans le formulaire')
        ->set('contract.seller_legal_name', 'Prestataire renseigné')
        ->set('contract.clause_payment', 'Paiement personnalisé du formulaire')
        ->set('contract.additional', 'Information complémentaire saisie')
        ->call('fillDocument')->assertHasNoErrors()->assertSet('showForm', false);
    $document = Document::findOrFail($component->get('previewId'));
    expect($document->finalized_at)->not->toBeNull()
        ->and($document->content)->toContain('Prestataire renseigné', 'Paiement personnalisé du formulaire', 'Information complémentaire saisie')
        ->and($document->snapshot['client']['name'])->toBe('Client renseigné dans le formulaire')
        ->and(Storage::disk('local')->get($document->path))->toStartWith('%PDF-')
        ->and($original->client->fresh()->name)->toBe($clientName);
    $this->get(route('admin.documents.download', $document))->assertOk();
})->with(['contract', 'terms']);

test('document type switches replace the form and discard stale content', function () {
    $original = completeLegalDocument();
    Livewire::test(CommercialDocuments::class)->call('revise', $original->id)
        ->set('contract.additional', 'Texte réservé au contrat')
        ->assertSeeHtml('id="legal-ip_mode"')
        ->set('form.type', 'terms')->assertSet('revisionOf', null)
        ->assertSeeHtml('wire:key="legal-form-terms"')
        ->assertDontSeeHtml('id="legal-ip_mode"')
        ->assertDontSee('Texte réservé au contrat')
        ->set('form.type', 'contract')->assertSeeHtml('id="legal-ip_mode"')
        ->set('form.type', 'other')->assertSeeHtml('id="document-content"')
        ->assertDontSeeHtml('id="legal-client_name"');
    Livewire::test(CommercialDocuments::class)->call('create')->set('form.type', 'terms')
        ->assertSet('mode', 'generate')->assertSeeHtml('id="legal-client_name"');
});

test('incomplete form does not create a document or PDF', function () {
    $original = completeLegalDocument();
    $count = Document::count();
    Livewire::test(CommercialDocuments::class)->call('revise', $original->id)
        ->set('contract.client_address', '   ')->call('fillDocument')
        ->assertHasErrors('contract.client_address')->assertSet('showForm', true);
    expect(Document::count())->toBe($count)->and($original->refresh()->path)->toBeNull();
});

test('template variables and inline terms can be completed without editing source records', function () {
    $original = completeLegalDocument('terms');
    $component = Livewire::test(CommercialDocuments::class)->call('revise', $original->id)
        ->set('contract.terms_template_id', '')
        ->set('contract.terms_name', 'CGV particulières')
        ->set('contract.terms_revision', '1')
        ->set('contract.terms_effective_on', today()->format('Y-m-d'))
        ->set('contract.terms_content', 'Interlocuteur : {{ interlocuteur }} chez {{seller}}.')
        ->assertSeeHtml('id="legal-variable_interlocuteur"')
        ->call('fillDocument')->assertHasErrors('contract.variable_interlocuteur')
        ->set('contract.variable_interlocuteur', 'Camille Dupont')
        ->call('fillDocument')->assertHasNoErrors();
    expect(Document::findOrFail($component->get('previewId'))->content)->toContain('Interlocuteur : Camille Dupont chez');
});

test('choosing another terms version refreshes its text and clauses', function () {
    $original = completeLegalDocument('terms');
    $settings = CommercialSetting::current();
    $settings['clause_payment'] = 'Paiement de la nouvelle version';
    CommercialSetting::findOrFail(1)->update(['values' => $settings]);
    $template = DocumentTemplate::create(['name' => 'CGV suivantes', 'type' => 'terms', 'content' => 'Texte suivant', 'effective_on' => today()->format('Y-m-d'), 'revision' => 2]);
    Livewire::test(CommercialDocuments::class)->call('revise', $original->id)
        ->set('contract.terms_template_id', (string) $template->id)
        ->assertSet('contract.terms_content', 'Texte suivant')
        ->assertSet('contract.clause_payment', 'Paiement de la nouvelle version')
        ->call('fillDocument')->assertHasNoErrors();
});

test('inactive contract fields neither display nor leak into the PDF', function () {
    $original = completeLegalDocument();
    $component = Livewire::test(CommercialDocuments::class)->call('revise', $original->id)
        ->set('contract.ip_territory', 'Ancien territoire réservé')
        ->set('contract.ip_mode', 'licence')
        ->set('contract.ip_license', 'Licence non exclusive')
        ->assertDontSeeHtml('id="legal-ip_territory"')
        ->assertSeeHtml('id="legal-ip_license"')
        ->call('fillDocument')->assertHasNoErrors();
    expect(Document::findOrFail($component->get('previewId'))->content)
        ->toContain('Licence non exclusive')->not->toContain('Ancien territoire réservé');
});

test('invalid version displays French guidance and targets the version field', function () {
    $document = completeLegalDocument('terms');
    Livewire::test(CommercialDocuments::class)->call('revise', $document->id)
        ->set('contract.terms_revision', 'v1')
        ->call('fillDocument')
        ->assertHasErrors(['contract.terms_revision' => 'integer'])
        ->assertSee('Le champ « Numéro de version » doit contenir un nombre entier (exemple : 1).')
        ->assertDontSee('validation.integer')
        ->assertDispatched('document-validation-failed', fn ($event, $params) => $params['fields'][0]['id'] === 'legal-terms_revision')
        ->assertSet('showForm', true)
        ->set('contract.terms_revision', '2')->call('fillDocument')->assertHasNoErrors();
});

test('missing information directs the user to the relevant input', function (string $key, string $value, string $target) {
    $document = completeLegalDocument();
    Livewire::test(CommercialDocuments::class)->call('revise', $document->id)
        ->set('contract.'.$key, $value)->call('fillDocument')
        ->assertDispatched('document-validation-failed', fn ($event, $params) => $params['fields'][0]['id'] === $target)
        ->assertSet('showForm', true);
})->with([
    ['client_address', '', 'legal-client_address'],
    ['client_audience', '', 'legal-client_audience'],
    ['project_starts_on', '', 'legal-project_starts_on'],
    ['ip_territory', '', 'legal-ip_territory'],
]);

function workflowDocument(string $type = 'contract'): Document
{
    $document = completeLegalDocument($type);
    $settings = CommercialSetting::current();
    $settings['siren'] = '123456789';
    CommercialSetting::findOrFail(1)->update(['values' => $settings]);
    $template = DocumentTemplate::findOrFail($document->contract_data['terms_template_id']);
    app(TermsVersions::class)->transition($template, 'active');
    $document->update(['engine_version' => 2, 'cgv_version_id' => $template->id]);

    return $document->fresh();
}

test('central resolver uses live identities and conditions without copying CGV into contracts', function () {
    $document = workflowDocument();
    $document->client->update(['address' => 'Adresse centrale actualisée']);
    $result = app(ProjectLegalDocuments::class)->prepare($document->fresh());
    expect($result['errors'])->toBe([])->and($result['snapshot']['client']['address'])->toContain('Adresse centrale actualisée')
        ->and($result['content'])->toContain('CGV Codenyr')->not->toContain('Clauses applicables', 'Texte général applicable');
});

test('active terms selection respects effective dates and retains historical versions', function () {
    $document = workflowDocument('terms');
    $old = $document->cgv_version_id;
    $next = DocumentTemplate::create(['name' => 'Version future', 'type' => 'terms', 'content' => 'Nouvelles règles', 'effective_on' => today()->addMonth()->format('Y-m-d'), 'revision' => 2, 'version_label' => '2.0']);
    $versions = app(TermsVersions::class);
    $versions->transition($next, 'active');
    expect($versions->applicable(today()->format('Y-m-d'))->id)->toBe($old)
        ->and($versions->applicable(today()->addMonths(2)->format('Y-m-d'))->id)->toBe($next->id);
});

test('controlled variables resolve known values and reject unknown ones', function () {
    $errors = [];
    $resolver = app(DocumentVariables::class);
    expect($resolver->resolve('{{ company.name }} / {{ unknown.value }}', ['company' => ['name' => 'Codenyr']], $errors))->toContain('Codenyr');
    expect($errors)->toHaveKey('variable_unknown_value');
});
