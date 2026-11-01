<?php

test('custom real estate demo renders every page with the customisation panel', function (string $page) {
    $response = $this->get(route('demo.custom', ['page' => $page]))->assertOk()
        ->assertSee('Personnaliser ce site')->assertSee('noindex,nofollow');
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with(['accueil', 'biens', 'bien', 'visite', 'estimation', 'espace', 'agence']);

test('custom demo exposes booking, client file and agency tools', function () {
    $this->get(route('demo.custom', ['page' => 'visite']))->assertOk()->assertSee('booking-form', false)->assertSee('data-slot-week', false)->assertSee('Créer mon dossier client');
    $this->get(route('demo.custom', ['page' => 'espace']))->assertOk()->assertSee('camille@seuil.demo');
    $this->get(route('demo.custom', ['page' => 'agence']))->assertOk()->assertSee('agence@seuil.demo')->assertSee('Seuil2026!')->assertSee('Créneaux d’ouverture', false);
    $this->assertGuest();
});

test('services and homepage link to the custom demo, unknown pages are rejected', function () {
    $this->get(route('services'))->assertOk()->assertSee(route('demo.custom'), false);
    $this->get(route('home'))->assertOk()->assertSee(route('demo.custom'), false);
    $this->get('/demonstrations/seuil/inconnue')->assertNotFound();
});
