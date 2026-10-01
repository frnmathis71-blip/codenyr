<?php

use App\Livewire\Admin\Billing;
use App\Livewire\Admin\ClientProjects;
use App\Livewire\Admin\CommercialDirectory;
use App\Livewire\Admin\CommercialDocuments;
use App\Livewire\Admin\ProjectDossier;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\CommercialSetting;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Price;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CommercialPdf;
use App\Services\InvoicePayments;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function commercialProject(): ClientProject
{
    return ClientProject::create(['client_id' => Client::create(['name' => 'Restaurant Sithinem', 'email' => 'contact@sithinem.test'])->id, 'name' => 'Site Restaurant Sithinem', 'type' => 'Site vitrine', 'status' => 'development']);
}

function commercialItem(array $overrides = []): array
{
    return array_replace(['name' => 'Site vitrine', 'description' => 'Création du site', 'price' => '1500', 'quantity' => '1', 'discount' => '0', 'discount_type' => 'percent', 'vat' => '20', 'unit' => 'forfait', 'frequency' => 'once', 'optional' => false, 'selected' => true], $overrides);
}

function commercialQuote(ClientProject $project): Quote
{
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([commercialItem(), commercialItem(['name' => 'Maintenance', 'price' => '39', 'frequency' => 'monthly'])], false);

    return Quote::create(['client_project_id' => $project->id, 'number' => 'DEV-2026-0001', 'issued_on' => today(), 'due_on' => today()->addDays(30), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 0, 'percent', 4000), 'snapshot' => app(CommercialBilling::class)->snapshot($project), 'deposit_type' => 'percent', 'deposit_value' => 4000]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

test('commercial pages render and existing public routes remain intact', function () {
    foreach (['/admin/projets', '/admin/clients', '/admin/catalogue', '/admin/parametres-commerciaux', '/admin/modeles-documents', '/admin/devis', '/admin/devis/nouveau', '/admin/factures', '/admin/factures/nouveau', '/admin/documents', '/admin/recherche', '/admin'] as $path) {
        $this->get($path)->assertOk();
    }
    $project = commercialProject();
    $this->get('/admin/projets/'.$project->id)->assertOk()->assertSee('Restaurant Sithinem');
    $this->get('/admin/devis/nouveau?project='.$project->id)->assertOk()->assertSee('Catalogue de prestations')->assertSee('Configuration')->assertSee('Site Vitrine');
    $this->get('/devis')->assertOk();
    $this->get('/admin/realisations')->assertOk();
});

test('clients cannot access commercial pages actions or private documents', function () {
    Storage::fake('local');
    $project = commercialProject();
    Storage::disk('local')->put('private.pdf', '%PDF-1.4');
    $document = Document::create(['client_id' => $project->client_id, 'name' => 'Secret', 'type' => 'terms', 'document_date' => today(), 'path' => 'private.pdf', 'mime' => 'application/pdf']);
    $this->actingAs(User::factory()->create());
    foreach (['/admin/projets', '/admin/clients', '/admin/catalogue', '/admin/devis', '/admin/documents', '/admin/factures', '/admin/documents/'.$document->id.'/telecharger', '/admin/documents/'.$document->id.'/apercu'] as $path) {
        $this->get($path)->assertForbidden();
    }
    Livewire::test(ClientProjects::class)->assertForbidden();
    Livewire::test(Billing::class, ['kind' => 'quotes'])->assertForbidden();
    Livewire::test(CommercialDocuments::class)->assertForbidden();
    $this->post('/logout');
    $this->get('/admin/documents/'.$document->id.'/telecharger')->assertRedirect('/login');
});

test('project creation creates a commercial identity without a login account', function () {
    Livewire::test(ClientProjects::class)->call('create')
        ->set('clientForm.name', 'Restaurant Sithinem')->set('clientForm.email', 'restaurant@example.test')
        ->set('form.name', 'Création du site')->call('save')->assertHasNoErrors();
    $project = ClientProject::first();
    expect($project->client->name)->toBe('Restaurant Sithinem')->and($project->starts_on)->toBeNull();
    expect(User::count())->toBe(1);
});

test('catalog prices remain linked to public pricing and initialization preserves edits', function () {
    Livewire::test(CommercialDirectory::class, ['module' => 'catalog']);
    $service = Service::where('price_key', 'offer_vitrine')->first();
    Livewire::test(CommercialDirectory::class, ['module' => 'catalog'])->call('edit', $service->id)->set('form.price', '1234.50')->call('save')->assertHasNoErrors();
    expect(Price::find('offer_vitrine')->amount_cents)->toBe(123450);
    Livewire::test(CommercialDirectory::class, ['module' => 'catalog']);
    expect($service->refresh()->currentPrice())->toBe(123450);
    $this->get('/tarifs')->assertSee('1 234,50');
});

test('project creation accepts optional websites and adds https to bare domains', function (string $input, ?string $expected) {
    Livewire::test(ClientProjects::class)->call('create')
        ->set('clientForm.name', 'Restaurant Sithinem')->set('clientForm.email', 'restaurant@example.test')
        ->set('form.name', 'Création du site')->set('form.website_url', $input)
        ->call('save')->assertHasNoErrors();

    expect(ClientProject::sole()->website_url)->toBe($expected);
})->with([
    'empty' => ['', null],
    'whitespace' => ['   ', null],
    'bare domain' => ['sithinem.fr', 'https://sithinem.fr'],
    'www and whitespace' => [' www.sithinem.fr ', 'https://www.sithinem.fr'],
    'complete URL' => ['https://sithinem.fr/contact', 'https://sithinem.fr/contact'],
    'explicit HTTP' => ['http://sithinem.fr', 'http://sithinem.fr'],
]);

test('invalid project websites show a useful French error without creating a dossier', function (string $input) {
    Livewire::test(ClientProjects::class)->call('create')
        ->set('clientForm.name', 'Restaurant Sithinem')->set('clientForm.email', 'restaurant@example.test')
        ->set('form.name', 'Création du site')->set('form.website_url', $input)
        ->call('save')->assertHasErrors('form.website_url')
        ->assertSee('Saisissez une adresse de site valide')->assertDontSee('validation.url');

    expect(ClientProject::count())->toBe(0)->and(Client::count())->toBe(0);
})->with(['pas une adresse', 'javascript:alert(1)', 'ftp://sithinem.fr']);

test('editing a project uses the same website normalization and French errors', function () {
    $project = commercialProject();
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project])->call('edit');
    $component->set('form.website_url', 'www.sithinem.fr')->call('save')->assertHasNoErrors();
    expect($project->refresh()->website_url)->toBe('https://www.sithinem.fr');

    $component->call('edit')->set('form.website_url', 'pas une adresse')->call('save')
        ->assertHasErrors('form.website_url')->assertSee('Saisissez une adresse de site valide')->assertDontSee('validation.url');
    expect($project->refresh()->website_url)->toBe('https://www.sithinem.fr');

    $component->set('form.website_url', '   ')->call('save')->assertHasNoErrors();
    expect($project->refresh()->website_url)->toBeNull();
});

test('financial calculations separate options recurrence discounts tax and deposit', function () {
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([commercialItem(['price' => '100.01', 'quantity' => '2', 'discount' => '10']), commercialItem(['price' => '39', 'frequency' => 'monthly']), commercialItem(['price' => '300', 'optional' => true, 'selected' => false])], true);
    $totals = $calculator->calculate($items, 'fixed', 1000, 'percent', 4000);
    expect($totals['initial']['subtotal'])->toBe(20002)->and($totals['initial']['ht'])->toBe(17002)->and($totals['initial']['vat'])->toBe(3400)->and($totals['initial']['ttc'])->toBe(20402)->and($totals['deposit'])->toBe(8161)->and($totals['remaining'])->toBe(12241);
    expect($totals['recurring']['monthly']['ttc'])->toBe(4680);
    expect(CommercialCalculator::decimal('1 234,56'))->toBe(123456);
});

test('invalid quantities percentage and excessive deposits are rejected server side', function () {
    $calculator = app(CommercialCalculator::class);
    expect(fn () => $calculator->normalize([commercialItem(['quantity' => '0'])], false))->toThrow(ValidationException::class);
    $items = $calculator->normalize([commercialItem()], false);
    expect(fn () => $calculator->calculate($items, 'percent', 10001))->toThrow(ValidationException::class);
    expect(fn () => $calculator->calculate($items, 'percent', 0, 'fixed', 99999999))->toThrow(ValidationException::class);
    expect(fn () => $calculator->calculate($items, 'fixed', 200000))->toThrow(ValidationException::class);
});

test('devis editor persists server totals and freezes historic prices', function () {
    $project = commercialProject();
    Livewire::test(Billing::class, ['kind' => 'quotes'])
        ->set('form', ['client_project_id' => $project->id, 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays(30)->format('Y-m-d'), 'discount_type' => 'percent', 'discount' => '0', 'deposit_type' => 'percent', 'deposit' => '40', 'conditions' => 'Conditions test', 'estimated_delay' => '30 jours', 'invoice_type' => 'standard'])
        ->set('items', [commercialItem(['vat' => '0'])])->call('save')->assertHasNoErrors();
    $quote = Quote::first();
    expect($quote->totals['initial']['ttc'])->toBe(150000)->and($quote->totals['deposit'])->toBe(60000);
    $project->client->update(['name' => 'Nouveau nom']);
    CommercialSetting::create(['id' => 1, 'values' => ['name' => 'Nouvelle marque']]);
    expect($quote->refresh()->snapshot['client']['name'])->toBe('Restaurant Sithinem')->and($quote->snapshot['seller']['name'])->toBe('Codenyr');
    $copy = app(CommercialBilling::class)->duplicate($quote);
    expect($copy->number)->not->toBe($quote->number)->and($copy->items)->toBe($quote->items);
});

test('issued invoices stay immutable and quote conversion does not double bill', function () {
    $project = commercialProject();
    $quote = commercialQuote($project);
    $billing = app(CommercialBilling::class);
    $billing->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $deposit = $billing->fromQuote($quote, 'deposit');
    expect($deposit->totals['initial']['ttc'])->toBe(60000);
    expect($billing->fromQuote($quote, 'deposit')->id)->toBe($deposit->id);
    $billing->finalize($deposit);
    $number = $deposit->number;
    expect(fn () => $deposit->update(['conditions' => 'rewrite']))->toThrow(LogicException::class);
    $deposit->refresh();
    expect(fn () => $deposit->delete())->toThrow(LogicException::class);
    $balance = $billing->fromQuote($quote, 'balance');
    expect($balance->totals['initial']['ttc'])->toBe(90000)->and($balance->totals['recurring']['monthly']['ttc'])->toBe(0);
    $billing->finalize($balance);
    expect($balance->number)->not->toBe($number);
    expect(fn () => $billing->fromQuote($quote, 'balance'))->toThrow(ValidationException::class);
});

test('payments are validated against the selected project and balance', function () {
    $project = commercialProject();
    $quote = commercialQuote($project);
    app(CommercialBilling::class)->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $invoice = app(CommercialBilling::class)->fromQuote($quote, 'deposit');
    app(CommercialBilling::class)->finalize($invoice);
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project])->set('tab', 'payments')->set('payment.invoice_id', $invoice->id)->set('payment.amount', '700')->call('pay')->assertHasErrors('payment.amount');
    expect(Payment::count())->toBe(0);
    $component->set('payment.amount', '200')->call('pay')->assertHasNoErrors();
    expect($invoice->refresh()->remainingCents())->toBe(40000);
    $component->set('payment.amount', '400')->call('pay')->assertHasNoErrors();
    expect($invoice->refresh()->remainingCents())->toBe(0)->and($project->checklist()['Acompte reçu'])->toBeTrue();
});

test('private uploads can be downloaded and signatures preserve originals', function () {
    Storage::fake('local');
    $project = commercialProject();
    $file = UploadedFile::fake()->image('logo.png');
    Livewire::test(CommercialDocuments::class)->call('create')->set('form.name', 'Logo client')->set('form.type', 'work')->set('form.client_project_id', $project->id)->set('upload', $file)->call('save')->assertHasNoErrors();
    $document = Document::first();
    Storage::disk('local')->assertExists($document->path);
    $this->get(route('admin.documents.download', $document))->assertOk()->assertDownload('logo.png');
    $original = Document::create(['client_id' => $project->client_id, 'client_project_id' => $project->id, 'name' => 'CGV v1', 'type' => 'terms', 'document_date' => today(), 'content' => 'Version initiale']);
    Livewire::test(CommercialDocuments::class)->call('sign', $original->id)->set('upload', UploadedFile::fake()->image('cgv-signees.png'))->call('save')->assertHasNoErrors();
    expect($original->refresh()->content)->toBe('Version initiale')->and($original->signedCopies()->count())->toBe(1)->and($project->checklist()['CGV signées'])->toBeTrue();
});

test('forged upload extensions and inconsistent document associations are rejected', function () {
    Storage::fake('local');
    $project = commercialProject();
    Livewire::test(CommercialDocuments::class)->call('create')->set('form.name', 'Faux PDF')->set('form.client_project_id', $project->id)->set('upload', UploadedFile::fake()->createWithContent('test.pdf', '<?php echo 1;'))->call('save')->assertHasErrors('upload');
    expect(Document::count())->toBe(0);
    $other = Client::create(['name' => 'Autre', 'email' => 'other@example.test']);
    Livewire::test(CommercialDocuments::class)->call('create')->set('mode', 'generate')->set('form.name', 'Contrat')->set('form.client_project_id', $project->id)->set('form.client_id', $other->id)->set('form.content', 'Contrat')->call('save')->assertHasErrors('form.client_id');
});

test('document templates snapshots PDFs and versions remain historical', function () {
    Storage::fake('local');
    $project = commercialProject();
    $template = DocumentTemplate::create(['name' => 'CGV', 'type' => 'terms', 'content' => 'Conditions de {{seller}} pour {{client}}.']);
    Livewire::test(CommercialDocuments::class)->call('create')->set('mode', 'generate')->set('form.client_project_id', $project->id)->set('form.template_id', $template->id)->call('save')->assertHasNoErrors();
    $document = Document::first();
    $template->update(['content' => 'Nouvelles conditions', 'revision' => 2]);
    expect($document->refresh()->content)->toBe('Conditions de Codenyr pour Restaurant Sithinem.');
    app(CommercialPdf::class)->document($document);
    expect(substr(Storage::disk('local')->get($document->refresh()->path), 0, 5))->toBe('%PDF-');
    $quote = commercialQuote($project);
    $pdf = app(CommercialPdf::class)->billing($quote);
    expect(substr(Storage::disk('local')->get($pdf->path), 0, 5))->toBe('%PDF-');
    $quote->update(['conditions' => 'Conditions ajoutées']);
    app(CommercialPdf::class)->billing($quote);
    expect($pdf->versions()->count())->toBe(2);
});

test('all project dossier tabs render and archived dossiers remain readable', function () {
    $project = commercialProject();
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project]);
    foreach (['summary', 'quotes', 'invoices', 'payments', 'documents', 'contracts', 'maintenance', 'files', 'notes', 'history'] as $tab) {
        $component->set('tab', $tab)->assertStatus(200);
    }
    $component->call('archive')->set('tab', 'summary')->assertSee('Archivé')->call('edit')->assertHasErrors('project');
    $component->call('archive')->call('edit')->assertSet('editing', true);
});

test('deposit interim and balance preserve exact VAT and cent totals', function () {
    $project = commercialProject();
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize([commercialItem(['price' => '83.33']), commercialItem(['name' => 'Conseil', 'price' => '47.89', 'vat' => '10', 'discount' => '7.5'])], true);
    $quote = Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today()->addDays(30), 'items' => $items, 'totals' => $calculator->calculate($items, 'percent', 500, 'percent', 4000), 'snapshot' => app(CommercialBilling::class)->snapshot($project)]);
    $billing = app(CommercialBilling::class);
    $billing->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $deposit = $billing->fromQuote($quote, 'deposit');
    expect($deposit->totals['initial']['ttc'])->toBe($quote->totals['deposit']);
    $billing->finalize($deposit);
    $interim = $billing->fromQuote($quote, 'interim', 1234);
    expect($interim->totals['initial']['ttc'])->toBe(1234);
    $billing->finalize($interim);
    $balance = $billing->fromQuote($quote, 'balance');
    $billing->finalize($balance);
    foreach (['ht', 'vat', 'ttc'] as $key) {
        expect($deposit->totals['initial'][$key] + $interim->totals['initial'][$key] + $balance->totals['initial'][$key])->toBe($quote->totals['initial'][$key]);
    }
});

test('saved editor routes render and finalized fields reject crafted Livewire edits', function () {
    Storage::fake('local');
    $project = commercialProject();
    $quote = commercialQuote($project);
    $this->get('/admin/devis/'.$quote->id)->assertOk()->assertSee('Configuration');
    $component = Livewire::test(Billing::class, ['kind' => 'quotes', 'id' => $quote->id]);
    $component->set('items.0.price', ['malformed'])->call('save')->assertHasErrors('items.0.price');
    expect($quote->refresh()->totals['initial']['ttc'])->toBe(150000);
    $component->set('items.0.price', '1500')->call('finalize')->assertHasNoErrors()->assertSee('Envoyé');
    $component->set('form.conditions', 'Réécriture')->call('save')->assertHasErrors('billing');
    expect($quote->refresh()->conditions)->not->toBe('Réécriture');
    $component->set('statusChoice', 'accepted')->call('changeStatus')->assertHasNoErrors();
    $invoice = app(CommercialBilling::class)->fromQuote($quote->refresh(), 'standard');
    $this->get('/admin/factures/'.$invoice->id)->assertOk()->assertSee('calculés depuis le devis');
    Livewire::test(Billing::class, ['kind' => 'invoices', 'id' => $invoice->id])->set('items.0.price', '1')->call('save')->assertHasNoErrors();
    expect($invoice->refresh()->totals['initial']['ttc'])->toBe(150000);
});

test('maintenance lifecycle and notes stay scoped to their project', function () {
    $project = commercialProject();
    $other = commercialProject();
    $component = Livewire::test(ProjectDossier::class, ['clientProject' => $project]);
    $component->set('tab', 'notes')->set('note', 'Le client doit fournir ses photos.')->set('pinned', true)->call('addNote')->assertHasNoErrors()->assertSee('Épinglée');
    $component->set('tab', 'maintenance')->set('maintenanceForm.amount', '39.90')->call('addMaintenance')->assertHasNoErrors();
    $contract = $project->maintenance()->first();
    $component->call('maintenanceStatus', $contract->id, 'active')->assertHasNoErrors();
    expect($contract->refresh()->status)->toBe('active')->and($contract->amount_cents)->toBe(3990)->and($other->maintenance()->count())->toBe(0);
    Livewire::test(ProjectDossier::class, ['clientProject' => $other])->call('maintenanceStatus', $contract->id, 'terminated')->assertNotFound();
});

test('explicit VAT applies to saved drafts despite a disabled global default and survives PDF finalization', function () {
    Storage::fake('local');
    $quote = commercialQuote(commercialProject());
    expect($quote->snapshot['seller']['vat_enabled'])->toBeFalse();
    $component = Livewire::test(Billing::class, ['kind' => 'quotes', 'id' => $quote->id])
        ->set('items.0.vat', '20')->set('items.1.vat', '5,5')
        ->assertViewHas('totals', fn ($totals) => $totals['initial']['ttc'] === 180000 && $totals['recurring']['monthly']['ttc'] === 4115)
        ->call('save')->assertHasNoErrors();
    expect($quote->refresh()->totals['initial']['vat'])->toBe(30000)
        ->and($quote->totals['deposit'])->toBe(72000)
        ->and($quote->snapshot['seller']['vat_enabled'])->toBeTrue();
    $component->call('finalize')->assertHasNoErrors();
    expect($quote->refresh()->items[0]['vat_basis'])->toBe(2000);
    $html = view('commercial.billing-pdf', ['record' => $quote, 'kind' => 'quote'])->render();
    expect($html)->toContain('20.00 %')->toContain('1 800,00 €');
    expect(Document::where('quote_id', $quote->id)->exists())->toBeTrue();
    $component->set('items.0.vat', '0')->call('save')->assertHasErrors('billing');
    expect($quote->refresh()->totals['initial']['vat'])->toBe(30000);
});

test('zero VAT is respected even when the global default enables tax', function () {
    CommercialSetting::create(['id' => 1, 'values' => ['vat_enabled' => true, 'vat_rate' => '20']]);
    $quote = commercialQuote(commercialProject());
    Livewire::test(Billing::class, ['kind' => 'quotes', 'id' => $quote->id])
        ->set('items.0.vat', '0')->call('save')->assertHasNoErrors();
    expect($quote->refresh()->totals['initial']['vat'])->toBe(0);
});

test('catalog subscription alone can be invoiced every month with VAT periods PDF and payments', function () {
    Storage::fake('local');
    $project = commercialProject();
    $service = Service::create(['name' => 'Abonnement mensuel', 'category' => 'Maintenance', 'description' => 'Suivi du site', 'unit' => 'mois', 'amount_cents' => 3900, 'frequency' => 'monthly', 'active' => true]);
    foreach (['2026-10-01' => '2026-10-31', '2026-11-01' => '2026-11-30'] as $start => $end) {
        $component = Livewire::test(Billing::class, ['kind' => 'invoices'])
            ->set('form', ['client_project_id' => $project->id, 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays(30)->format('Y-m-d'), 'discount_type' => 'percent', 'discount' => '0', 'deposit_type' => 'none', 'deposit' => '0', 'conditions' => '', 'estimated_delay' => '', 'invoice_type' => 'standard'])
            ->call('toggleService', $service->id)->assertHasNoErrors()
            ->set('items.0.vat', '20')->set('items.0.period_start', $start)->set('items.0.period_end', $end)
            ->call('save')->assertHasNoErrors()
            ->assertSet('items.0.frequency', 'monthly')->assertSet('items.0.period_start', $start);
        $invoice = Invoice::latest('id')->first();
        $this->get('/admin/factures/'.$invoice->id)->assertOk()->assertSee('Période facturée : du')->assertSee('46,80 €');
        Livewire::test(Billing::class, ['kind' => 'invoices', 'id' => $invoice->id])
            ->assertSet('items.0.period_end', $end)
            ->assertViewHas('totals', fn ($totals) => $totals['initial']['ttc'] === 4680);
        expect($invoice->items)->toHaveCount(1)
            ->and($invoice->items[0]['frequency'])->toBe('once')
            ->and($invoice->items[0]['source_frequency'])->toBe('monthly')
            ->and($invoice->items[0]['period_end'])->toBe($end)
            ->and($invoice->totals['initial']['ht'])->toBe(3900)
            ->and($invoice->totals['initial']['vat'])->toBe(780)
            ->and($invoice->totals['initial']['ttc'])->toBe(4680)
            ->and($invoice->totals['recurring']['monthly']['ttc'])->toBe(0);
        $component->call('save')->call('finalize')->assertHasNoErrors();
        expect($invoice->refresh()->remainingCents())->toBe(4680);
        $html = view('commercial.billing-pdf', ['record' => $invoice, 'kind' => 'invoice'])->render();
        expect($html)->toContain('Période facturée : du '.Carbon::parse($start)->format('d/m/Y'))
            ->toContain(Carbon::parse($end)->format('d/m/Y'))->toContain('46,80 €');
        expect(Document::where('invoice_id', $invoice->id)->exists())->toBeTrue();
        Livewire::test(ProjectDossier::class, ['clientProject' => $project])
            ->set('payment.invoice_id', $invoice->id)->set('payment.amount', '46.80')->call('pay')->assertHasNoErrors();
        expect($invoice->refresh()->remainingCents())->toBe(0);
    }
    expect(Invoice::count())->toBe(2)->and(Invoice::pluck('number')->unique())->toHaveCount(2);
});

test('subscription invoices reject missing invalid or reversed billing periods', function (string $start, string $end, string $error) {
    $project = commercialProject();
    Livewire::test(Billing::class, ['kind' => 'invoices'])
        ->set('form', ['client_project_id' => $project->id, 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays(30)->format('Y-m-d'), 'discount_type' => 'percent', 'discount' => '0', 'deposit_type' => 'none', 'deposit' => '0', 'conditions' => '', 'estimated_delay' => '', 'invoice_type' => 'standard'])
        ->set('items', [commercialItem(['frequency' => 'monthly', 'period_start' => $start, 'period_end' => $end])])
        ->call('save')->assertHasErrors($error);
    expect(Invoice::count())->toBe(0);
})->with([
    ['', '2026-10-31', 'start'], ['2026-10-01', '', 'end'],
    ['2026-10-01', '2026-09-30', 'end'], ['2026-02-30', '2026-03-31', 'start'],
]);

test('invoice screen updates payment state and corrects mistakes without rewriting the invoice', function () {
    $project = commercialProject();
    $quote = commercialQuote($project);
    $billing = app(CommercialBilling::class);
    $billing->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $invoice = $billing->fromQuote($quote, 'standard');
    $billing->finalize($invoice);
    $originalItems = $invoice->items;
    $component = Livewire::test(Billing::class, ['kind' => 'invoices', 'id' => $invoice->id])
        ->assertSee('État et règlements')->set('payment.amount', '200')->call('pay')->assertHasNoErrors()->assertSee('Partiellement payée');
    $first = Payment::where('invoice_id', $invoice->id)->first();
    $component->call('fillBalance')->assertSet('payment.amount', '1300.00')->call('pay')->assertHasNoErrors();
    expect($invoice->refresh()->paymentLabel())->toBe('Payée');
    $component->call('voidPayment', $first->id)->assertHasErrors('correctionReason');
    $component->set('correctionReason', 'Règlement enregistré sur la mauvaise facture')->call('voidPayment', $first->id)->assertHasNoErrors();
    expect($invoice->refresh()->paymentLabel())->toBe('Partiellement payée')
        ->and($invoice->remainingCents())->toBe(20000)->and($invoice->items)->toBe($originalItems)
        ->and($first->refresh()->voided_at)->not->toBeNull()->and(Payment::count())->toBe(2);
    $component->set('correctionReason', 'Même correction renvoyée')->call('voidPayment', $first->id)->assertHasNoErrors();
    expect($invoice->refresh()->remainingCents())->toBe(20000);
});

test('invoice payments reject drafts future dates excess amounts and foreign corrections', function () {
    $project = commercialProject();
    $quote = commercialQuote($project);
    $billing = app(CommercialBilling::class);
    $billing->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $invoice = $billing->fromQuote($quote, 'standard');
    $component = Livewire::test(Billing::class, ['kind' => 'invoices', 'id' => $invoice->id])->set('payment.amount', '10')->call('pay')->assertHasErrors('payment.amount');
    $billing->finalize($invoice);
    $component->set('payment.paid_on', today()->addDay()->format('Y-m-d'))->call('pay')->assertHasErrors('payment.paid_on');
    $component->set('payment.paid_on', today()->format('Y-m-d'))->set('payment.amount', '1501')->call('pay')->assertHasErrors('payment.amount');
    expect(Payment::count())->toBe(0);
    $component->set('correctionReason', 'Correction demandée')->call('voidPayment', 99999)->assertNotFound();
    $key = (string) Str::uuid();
    $data = ['amount' => '10', 'paid_on' => today()->format('Y-m-d'), 'method' => 'transfer'];
    app(InvoicePayments::class)->record($invoice->id, $data, $key);
    app(InvoicePayments::class)->record($invoice->id, $data, $key);
    expect(Payment::count())->toBe(1);
});
