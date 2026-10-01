<?php

test('pro demo renders its public and simulated management pages', function (string $page) {
    $response = $this->get(route('demo.pro', ['page' => $page]))->assertOk()->assertSee('Plus de possibilités')->assertSee('noindex,nofollow');
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with(['accueil', 'studio', 'realisations', 'journal', 'gestion', 'connexion']);

test('services links to the pro demo and unknown demo pages are rejected', function () {
    $this->get(route('services'))->assertOk()->assertSee(route('demo.pro'), false);
    $this->get('/demonstrations/canopee/inconnue')->assertNotFound();
});

 test('pro demonstration provides fictional credentials and business modules', function () {
    $this->get(route('demo.pro', ['page' => 'connexion']))->assertOk()->assertSee('admin@canopee.demo')->assertSee('Canopee2026!')->assertSee('data-admin-url', false);
    $this->get(route('demo.pro', ['page' => 'gestion']))->assertOk()->assertSee('Demandes &amp; devis', false)->assertSee('Rendez-vous')->assertSee('client-form')->assertSee('business-settings');
    $this->assertGuest();
});
