<?php

test('pro demo renders its public and simulated management pages', function (string $page) {
    $response = $this->get(route('demo.pro', ['page' => $page]))->assertOk()->assertSee('Plus de possibilités')->assertSee('noindex,nofollow');
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with(['accueil', 'studio', 'realisations', 'journal', 'gestion']);

test('services links to the pro demo and unknown demo pages are rejected', function () {
    $this->get(route('services'))->assertOk()->assertSee(route('demo.pro'), false);
    $this->get('/demonstrations/canopee/inconnue')->assertNotFound();
});
