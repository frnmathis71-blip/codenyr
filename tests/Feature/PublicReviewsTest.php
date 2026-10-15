<?php

use App\Models\Testimonial;

test('reviews page lists every published review with pagination and hides private reviews', function () {
    foreach (range(1, 13) as $number) {
        Testimonial::create(['client_name' => 'Client '.$number, 'company' => 'Entreprise '.$number, 'content' => 'Un témoignage détaillé du client '.$number, 'rating' => 4, 'published' => true, 'moderation_status' => 'approved', 'created_at' => now()->subMinutes($number)]);
    }
    Testimonial::create(['client_name' => 'Avis confidentiel', 'content' => 'Ce contenu ne doit pas être publié.', 'rating' => 5, 'published' => false, 'moderation_status' => 'pending']);

    $this->get('/avis')->assertOk()->assertSee('13 avis clients publiés')->assertSee('Client 1')->assertSee('Entreprise 1')->assertSee('Note : 4 sur 5')->assertDontSee('Client 13')->assertDontSee('Avis confidentiel');
    $this->get('/avis?page=2')->assertOk()->assertSee('Client 13')->assertDontSee('Avis confidentiel');
    $this->get('/sitemap.xml')->assertOk()->assertSee(url('/avis'));
});

test('reviews page provides an honest empty state', function () {
    $this->get('/avis')->assertOk()->assertSee('Les premiers avis arrivent bientôt.');
});

test('published reviews show their date, their origin and how they are collected', function () {
    Testimonial::create(['client_name' => 'Client direct', 'content' => 'Un avis transmis directement à Codenyr.', 'rating' => 5, 'published' => true, 'moderation_status' => 'approved', 'created_at' => '2026-09-14 10:00:00']);

    $this->get(route('reviews'))->assertOk()
        ->assertSee('Avis du 14 septembre 2026')
        ->assertSee('Recueilli par Codenyr auprès du client')
        ->assertSee('Comment les avis sont recueillis');
    $this->get(route('home'))->assertOk()->assertSee(route('reviews').'#fonctionnement-avis', false);
});
