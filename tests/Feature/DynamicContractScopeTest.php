<?php

use App\Livewire\Admin\Billing;
use App\Livewire\Admin\CommercialDirectory;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Services\CommercialBilling;
use App\Services\CommercialCalculator;
use App\Services\CommercialCatalog;
use App\Services\ContractScopeBuilder;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    app(CommercialCatalog::class)->initialize();
});

function scopedQuote(string $offer, array $options = []): Quote
{
    $project = ClientProject::create(['client_id' => Client::create(['name' => 'Restaurant', 'email' => 'restaurant@example.test'])->id, 'name' => 'Projet', 'type' => 'Site']);
    $items = [];
    foreach ([$offer, ...$options] as $key) {
        $service = Service::where('price_key', $key)->orWhere('catalog_key', $key)->firstOrFail();
        $items[] = ['service_id' => $service->id, 'contract_key' => $key, 'contract_rules' => $service->contract_rules, 'name' => $service->name, 'description' => $service->description ?? '', 'price' => '100', 'quantity' => '1', 'discount' => '0', 'discount_type' => 'percent', 'vat' => '0', 'unit' => 'forfait', 'frequency' => $service->frequency, 'optional' => false, 'selected' => true];
    }
    $calculator = app(CommercialCalculator::class);
    $items = $calculator->normalize($items, false);
    $quote = Quote::create(['client_project_id' => $project->id, 'issued_on' => today(), 'due_on' => today()->addMonth(), 'items' => $items, 'totals' => $calculator->calculate($items), 'snapshot' => app(CommercialBilling::class)->snapshot($project)]);

    return $quote;
}

test('each offer only promises the options purchased', function (string $offer, array $options) {
    $quote = scopedQuote($offer, $options);
    app(CommercialBilling::class)->finalize($quote);
    $scope = app(ContractScopeBuilder::class)->build($quote);
    expect($scope['errors'])->toBe([])->and($scope['frozen'])->toBeTrue();
    foreach (['booking', 'stripe', 'user_accounts', 'contact_form'] as $key) {
        expect(array_key_exists($key, $scope['included']))->toBe(in_array($key, $options));
        if (in_array($key, $options)) {
            expect($scope['excluded'])->not->toHaveKey($key);
        }
    }
    expect(array_intersect_key($scope['included'], $scope['excluded']))->toBe([]);
    expect($scope['fields']['maintenance_mode'])->toBe(in_array('maintenance_essential', $options) ? 'essentiel' : 'aucune');
    expect($scope['fields']['privacy_processing'])->toBe(array_intersect($options, ['booking', 'contact_form', 'user_accounts']) ? 'oui' : 'non');
    if (in_array('stripe', $options)) {
        expect(implode(' ', $scope['clauses']))->toContain('n’est pas l’établissement de paiement');
    }
})->with([
    ['offer_landing', []], ['offer_landing', ['contact_form']],
    ['offer_vitrine', []], ['offer_vitrine', ['booking']],
    ['offer_vitrine', ['booking', 'maintenance_essential']],
    ['offer_pro', []], ['offer_pro', ['user_accounts']], ['offer_pro', ['stripe']],
    ['offer_custom', []], ['offer_custom', ['gallery']],
]);

test('removed defaults custom features and unchecked options survive catalog changes', function () {
    $quote = scopedQuote('offer_custom', ['booking']);
    $items = $quote->items;
    $items[0]['contract_rules']['deliverables'] = [];
    $items[1]['optional'] = true;
    $items[1]['selected'] = false;
    $items[] = [...$items[1], 'service_id' => null, 'contract_key' => null, 'name' => 'Carte des restaurants partenaires', 'description' => 'Carte interactive', 'optional' => false, 'selected' => true, 'contract_rules' => []];
    $quote->update(['items' => $items, 'totals' => app(CommercialCalculator::class)->calculate($items)]);
    app(CommercialBilling::class)->finalize($quote);
    $quote->update(['status' => 'accepted']);
    $before = app(ContractScopeBuilder::class)->build($quote);
    Service::where('price_key', 'offer_custom')->firstOrFail()->update(['contract_rules' => ['deliverables' => ['stripe' => 'Paiement promis plus tard']]]);
    Service::where('catalog_key', 'booking')->firstOrFail()->update(['name' => 'Réservation modifiée']);
    expect(app(ContractScopeBuilder::class)->build($quote->fresh()))->toBe($before);
    expect($before['scope'])->toContain('Carte des restaurants partenaires')->not->toContain('Paiement promis', 'Réservation en ligne —');
    expect($before['custom'])->toBe(['Carte des restaurants partenaires']);
    expect($quote->snapshot['offer_snapshot']['items'])->toBe($items);
    expect(fn () => $quote->update(['items' => []]))->toThrow(LogicException::class);
});

test('maintenance and infrastructure effects are data driven', function (string $mode) {
    $quote = scopedQuote('offer_vitrine', ['hosting', 'domain', 'maintenance_'.$mode]);
    app(CommercialBilling::class)->finalize($quote);
    $scope = app(ContractScopeBuilder::class)->build($quote);
    expect($scope['fields']['maintenance_mode'])->toBe($mode === 'essential' ? 'essentiel' : $mode);
    foreach (['domain', 'hosting'] as $kind) {
        foreach (['mode', 'owner', 'payer', 'renewal'] as $field) {
            expect($scope['fields'][$kind.'_'.$field])->toBe('codenyr');
        }
    }
    expect($scope['clauses']['Maintenance'])->not->toBeEmpty();
})->with(['essential', 'pro', 'business']);

test('catalog editing and quote line editing preserve explicit removals', function () {
    $offer = Service::where('price_key', 'offer_landing')->firstOrFail();
    Livewire::test(CommercialDirectory::class, ['module' => 'catalog'])->call('edit', $offer->id)
        ->set('contractRules.deliverables', 'Page de campagne')->set('contractRules.revision_rounds', '0')
        ->call('save')->assertHasNoErrors();
    app(CommercialCatalog::class)->initialize();
    expect(array_values($offer->refresh()->contract_rules['deliverables']))->toBe(['Page de campagne']);
    $quote = scopedQuote('offer_landing');
    Livewire::test(Billing::class, ['kind' => 'quotes', 'id' => $quote->id])
        ->set('ruleEditors.0.deliverables', 'Page simplifiée sans formulaire')
        ->call('save')->assertHasNoErrors();
    app(CommercialBilling::class)->finalize($quote->refresh());
    $scope = app(ContractScopeBuilder::class)->build($quote);
    expect($scope['scope'])->toContain('Page simplifiée sans formulaire')->not->toContain('Page de campagne');
    expect($scope['fields']['revision_rounds'])->toBe('0');
});

test('conflicting maintenance options are reported and unknown page counts are never invented', function () {
    $quote = scopedQuote('offer_vitrine', ['maintenance_essential', 'maintenance_pro']);
    $scope = app(ContractScopeBuilder::class)->build($quote);
    expect($scope['errors'])->toHaveKey('effect_maintenance_mode');
    expect($scope['scope'])->not->toContain('5 pages', '10 pages');
});
