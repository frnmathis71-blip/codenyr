<?php

test('vitrine demo pages are accessible with the customization panel', function (string $page) {
    $response = $this->get(route('demo.vitrine', ['page' => $page]))->assertOk()
        ->assertSee('Plus de possibilités')->assertSee('noindex,nofollow');
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with(['accueil', 'atelier', 'expertises', 'realisations', 'contact']);

test('vitrine example links locally and rejects unknown pages', function () {
    $this->get(route('services'))->assertOk()->assertSee(route('demo.vitrine'), false);
    $this->get('/demonstrations/atelier-rive/inconnue')->assertNotFound();
});
