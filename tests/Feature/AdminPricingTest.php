<?php

use App\Livewire\Admin\Pricing;
use App\Models\Price;
use App\Models\User;
use Livewire\Livewire;

test('only administrators can manage prices', function () {
    $this->get('/admin/tarifs')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/tarifs')->assertForbidden();
    Livewire::test(Pricing::class)->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/tarifs')->assertOk();
});

test('saved offer and maintenance prices appear across the site', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Pricing::class)->assertSet('prices.offer_vitrine', '790,00')
        ->set('prices.offer_vitrine', '1 234,50')->set('prices.maintenance_essential', '24,90')
        ->call('save')->assertHasNoErrors()->assertSee('Tarifs enregistrés');
    expect(Price::find('offer_vitrine')->amount_cents)->toBe(123450);
    expect(Price::find('maintenance_essential')->amount_cents)->toBe(2490);
    foreach (['/', '/services', '/tarifs'] as $path) {
        $this->get($path)->assertOk()->assertSee('1 234,50');
    }
    $this->get('/tarifs')->assertSee('24,90')->assertDontSee('19,90');
    Livewire::test(Pricing::class)->assertSet('prices.offer_vitrine', '1234,50');
});

test('invalid prices are rejected without partially saving', function (string $value) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Pricing::class)->set('prices.offer_landing', '600')->set('prices.offer_vitrine', $value)
        ->call('save')->assertHasErrors('prices.offer_vitrine');
    expect(Price::count())->toBe(0);
})->with(['', '-10', '12.345', '1000000', 'gratuit']);

test('malformed price inputs are rejected', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Pricing::class)->set('prices.offer_vitrine', ['unexpected'])
        ->call('save')->assertHasErrors('prices.offer_vitrine');
    expect(Price::count())->toBe(0);
});
