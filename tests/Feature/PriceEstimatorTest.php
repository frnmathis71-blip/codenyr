<?php

use App\Livewire\InquiryForm;
use App\Models\Service;
use App\Services\PriceEstimator;
use Livewire\Livewire;

function estimatorService(array $attributes): Service
{
    return Service::create([...['description' => null, 'unit' => 'forfait', 'frequency' => 'once', 'active' => true, 'pricing_mode' => 'fixed'], ...$attributes]);
}

test('the estimator follows the catalogue prices and offer compatibility', function () {
    Service::query()->delete();
    $page = estimatorService(['category' => 'Contenu / Structure', 'name' => 'Page supplémentaire', 'pricing_mode' => 'unit', 'unit' => 'page', 'amount_cents' => 9000, 'compatible_offers' => ['offer_vitrine', 'offer_pro']]);
    $admin = estimatorService(['category' => 'Administration', 'name' => 'Espace administrateur', 'amount_cents' => 25000, 'compatible_offers' => ['offer_landing', 'offer_vitrine']]);
    $booking = estimatorService(['category' => 'Fonctionnalités', 'name' => 'Réservation en ligne', 'pricing_mode' => 'starting', 'amount_cents' => 30000]);
    $hourly = estimatorService(['category' => 'Intervention', 'name' => 'Intervention ponctuelle', 'pricing_mode' => 'hourly', 'unit' => 'heure', 'amount_cents' => 6000]);
    $quoteOnly = estimatorService(['category' => 'Options', 'name' => 'Blog', 'pricing_mode' => 'quote', 'amount_cents' => 0]);
    $archived = estimatorService(['category' => 'Communication', 'name' => 'Ancienne option', 'amount_cents' => 5000, 'archived_at' => now()]);

    $data = app(PriceEstimator::class)->data();

    expect($data['offers']['vitrine'])->toMatchArray(['amount' => 79000, 'included' => 5, 'unit' => 'page', 'extra' => 9000])
        ->and($data['offers']['vitrine']['options'])->toEqualCanonicalizing([$admin->id, $booking->id])
        ->and($data['offers']['pro']['options'])->toBe([$booking->id])
        ->and($data['offers']['custom']['included'])->toBeNull()
        ->and($data['options'])->not->toHaveKeys([$page->id, $hourly->id, $quoteOnly->id, $archived->id])
        ->and($data['options'][$booking->id])->toMatchArray(['amount' => 30000, 'starting' => true])
        ->and($data['maintenance'])->toHaveKeys(['essential', 'pro', 'business']);
});

test('the services page offers the estimator and the jump link', function () {
    $this->get(route('services'))->assertOk()
        ->assertSee('id="estimer"', false)
        ->assertSee('Estimer mon budget')
        ->assertSee('priceEstimator(', false)
        ->assertSee('Recevoir mon devis précis');
});

test('an estimate prefills the quote request as editable plain text', function () {
    Livewire::withQueryParams(['offer' => 'Site Vitrine', 'estimate' => "Estimation réalisée sur le site :\n- Site Vitrine (base)<b>x</b>"])
        ->test(InquiryForm::class)
        ->assertSet('project_type', 'Site Vitrine')
        ->assertSet('description', "Estimation réalisée sur le site :\n- Site Vitrine (base)x\n\nMon projet en quelques mots : ");
});

test('an estimate is ignored on the contact form', function () {
    Livewire::withQueryParams(['estimate' => 'Estimation'])
        ->test(InquiryForm::class, ['mode' => 'contact'])
        ->assertSet('description', '');
});
