<?php

use App\Livewire\Admin\Pricing;
use App\Models\Price;
use App\Models\User;
use App\Services\PricingCatalog;
use Illuminate\Support\Facades\DB;
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

test('the complete price catalog reads prices once and reflects subsequent updates', function () {
    Price::create(['key' => 'offer_vitrine', 'amount_cents' => 123456]);
    $catalog = app(PricingCatalog::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $plans = $catalog->all();
        expect(DB::getQueryLog())->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($plans['offer_vitrine']['amount_cents'])->toBe(123456)
        ->and($plans)->toHaveKey('maintenance_essential');
    Price::findOrFail('offer_vitrine')->update(['amount_cents' => 98765]);
    expect($catalog->all()['offer_vitrine']['amount_cents'])->toBe(98765);
});

test('public price editing stores cent amounts exactly through the commercial calculator', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Pricing::class)->set('prices.offer_vitrine', '999 999,99')
        ->set('prices.maintenance_essential', '0,01')->call('save')->assertHasNoErrors();
    expect(Price::findOrFail('offer_vitrine')->amount_cents)->toBe(99999999)
        ->and(Price::findOrFail('maintenance_essential')->amount_cents)->toBe(1);
});
