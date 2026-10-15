<?php

use App\Livewire\InquiryForm;
use Livewire\Livewire;

test('each product demo ships the shared customisation panel with its own identity', function (string $route, string $product, string $defaultLayout) {
    $response = $this->get(route($route))->assertOk()
        ->assertSee('Personnaliser ce site')
        ->assertSee('id="cz-panel"', false)
        ->assertSee('data-brand', false)
        ->assertSee(route('quote', ['offer' => $product]), false);

    if ($defaultLayout !== '') {
        $response->assertSee('data-layout="'.$defaultLayout.'"', false);
    }
})->with([
    'landing' => ['demo.landing', 'Landing Page', ''],
    'vitrine' => ['demo.vitrine', 'Site Vitrine', 'split'],
    'pro' => ['demo.pro', 'Site Pro', 'immersive'],
]);

test('the pro demo keeps options the vitrine does not offer', function () {
    $this->get(route('demo.pro'))->assertSee('Immersive — photo plein écran')->assertSee('value="tiles"', false);
    $this->get(route('demo.vitrine'))->assertDontSee('Immersive — photo plein écran')->assertDontSee('value="tiles"', false);
});

test('a design chosen in a demo prefills the quote request without markup', function () {
    Livewire::withQueryParams(['offer' => 'Site Vitrine', 'design' => 'Démo Atelier Rive — Palette : Olive & lin <script>x</script>'])
        ->test(InquiryForm::class)
        ->assertSet('project_type', 'Site Vitrine')
        ->assertSet('description', fn (string $value) => str_contains($value, 'Palette : Olive & lin') && ! str_contains($value, '<script>'));
});
