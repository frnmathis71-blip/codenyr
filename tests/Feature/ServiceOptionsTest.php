<?php

use App\Livewire\Admin\Billing;
use App\Livewire\Admin\CommercialDirectory;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCatalog;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    app(CommercialCatalog::class)->initialize();
});

test('restaurant quote separates maintenance and preserves all snapshots after catalog edits', function () {
    $project = ClientProject::create(['client_id' => Client::create(['name' => 'Restaurant Exemple', 'email' => 'restaurant@example.test'])->id, 'name' => 'Restaurant Exemple', 'type' => 'Site Vitrine']);
    $page = Service::where('catalog_key', 'extra_page')->firstOrFail();
    $booking = Service::where('catalog_key', 'booking')->firstOrFail();
    $editor = Livewire::test(Billing::class, ['kind' => 'quotes'])
        ->set('form', ['client_project_id' => $project->id, 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays(30)->format('Y-m-d'), 'discount_type' => 'percent', 'discount' => '0', 'deposit_type' => 'none', 'deposit' => '0', 'conditions' => '', 'estimated_delay' => '', 'invoice_type' => 'standard'])
        ->set('offerChoice', 'offer_vitrine')
        ->call('toggleService', $page->id)->set('items.1.quantity', '2')
        ->call('toggleService', $booking->id)
        ->call('toggleService', Service::where('catalog_key', 'language')->firstOrFail()->id)
        ->call('toggleService', Service::where('price_key', 'maintenance_pro')->firstOrFail()->id)
        ->call('save')->assertHasNoErrors();
    $quote = Quote::firstOrFail();
    expect($quote->totals['initial']['ttc'])->toBe(142000)
        ->and($quote->totals['recurring']['monthly']['ttc'])->toBe(3990)
        ->and($quote->items[1]['category'])->toBe('Contenu / Structure')
        ->and($quote->items[1]['unit'])->toBe('page');
    $page->update(['amount_cents' => 11000, 'name' => 'Nouveau nom', 'category' => 'Autre', 'active' => false, 'archived_at' => now()]);
    app(CommercialCatalog::class)->initialize();
    $editor = Livewire::test(Billing::class, ['kind' => 'quotes', 'id' => $quote->id]);
    $editor->call('save')->assertHasNoErrors();
    expect($quote->refresh()->items[1]['price_cents'])->toBe(9000)
        ->and($quote->items[1]['name'])->toBe('Page supplémentaire')
        ->and($quote->items[1]['category'])->toBe('Contenu / Structure')
        ->and($page->refresh()->amount_cents)->toBe(11000);
    $editor->set('items.2.price', '450')->call('save')->assertHasNoErrors();
    expect($quote->refresh()->totals['initial']['ttc'])->toBe(157000)
        ->and($booking->refresh()->amount_cents)->toBe(30000);
});

test('catalog filters compatibility archive and custom recurring prices work', function () {
    expect(Service::whereNotNull('catalog_key')->count())->toBe(25);
    $crud = Service::where('catalog_key', 'extra_crud')->firstOrFail();
    Livewire::test(CommercialDirectory::class, ['module' => 'catalog'])
        ->set('offerFilter', 'offer_landing')->assertDontSee('Module CRUD supplémentaire')
        ->set('offerFilter', 'offer_pro')->assertSee('Module CRUD supplémentaire')
        ->call('archive', $crud->id)->assertDontSee('Module CRUD supplémentaire')
        ->set('stateFilter', 'archived')->assertSee('Module CRUD supplémentaire');
    app(CommercialCatalog::class)->initialize();
    expect($crud->refresh()->archived_at)->not->toBeNull();
    Livewire::test(CommercialDirectory::class, ['module' => 'catalog'])->call('create')
        ->set('form.name', 'API mensuelle')->set('form.category', 'Frais tiers')
        ->set('form.price', '12,90')->set('form.pricing_mode', 'monthly')->call('save')->assertHasNoErrors();
    expect(Service::where('name', 'API mensuelle')->firstOrFail()->frequency)->toBe('monthly');
});

test('offer selection replaces the base offer without adding included administration', function () {
    $editor = Livewire::test(Billing::class, ['kind' => 'quotes'])
        ->set('offerChoice', 'offer_vitrine')->set('offerChoice', 'offer_pro');
    expect($editor->get('items'))->toHaveCount(1);
    expect($editor->get('items')[0]['name'])->toBe('Site Pro');
    $this->get('/admin/devis/nouveau')->assertOk();
    $editor->assertViewHas('services', fn ($services) => ! $services->contains('catalog_key', 'admin_area'))
        ->set('showAllServices', true)->assertViewHas('services', fn ($services) => $services->contains('catalog_key', 'admin_area'));
});

test('direct third party fees are excluded and unknown prices require a quote amount', function () {
    $project = ClientProject::create(['client_id' => Client::create(['name' => 'Client', 'email' => 'client@example.test'])->id, 'name' => 'Projet', 'type' => 'Site Vitrine']);
    $editor = Livewire::test(Billing::class, ['kind' => 'quotes'])
        ->set('form', ['client_project_id' => $project->id, 'issued_on' => today()->format('Y-m-d'), 'due_on' => today()->addDays(30)->format('Y-m-d'), 'discount_type' => 'percent', 'discount' => '0', 'deposit_type' => 'none', 'deposit' => '0', 'conditions' => '', 'estimated_delay' => '', 'invoice_type' => 'standard'])
        ->set('offerChoice', 'offer_vitrine')
        ->call('toggleService', Service::where('catalog_key', 'third_party')->firstOrFail()->id)
        ->call('save')->assertHasErrors()
        ->set('items.1.price', '120')->set('items.1.payee', 'client')->call('save')->assertHasNoErrors();
    $quote = Quote::firstOrFail();
    expect($quote->totals['initial']['ttc'])->toBe(79000)
        ->and($quote->items[1]['payee'])->toBe('client');
    $html = view('commercial.billing-pdf', ['record' => $quote, 'kind' => 'quote'])->render();
    expect($html)->toContain('hors total Codenyr')->toContain('120,00');
    $billing = app(CommercialBilling::class);
    $copy = $billing->duplicate($quote);
    $billing->finalize($copy);
    $copy->update(['status' => 'accepted']);
    $invoice = $billing->fromQuote($copy, 'standard');
    expect($invoice->items)->toHaveCount(1)
        ->and($invoice->totals['initial']['ttc'])->toBe(79000);
    $editor->set('items.1.frequency', 'monthly')->call('save')->assertHasNoErrors();
    expect($quote->refresh()->totals['recurring']['monthly']['ttc'])->toBe(0);
    $editor->set('items.1.frequency', 'once');
    $editor->set('items.1.payee', 'rebilled')->call('save')->assertHasNoErrors();
    expect($quote->refresh()->totals['initial']['ttc'])->toBe(91000);
});
