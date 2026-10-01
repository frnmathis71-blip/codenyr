<?php

use App\Livewire\Admin\Leads;
use App\Livewire\InquiryForm;
use App\Models\Lead;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('legal pages and social metadata are available', function () {
    $this->get('/cgu')->assertOk()->assertSee('Conditions générales d’utilisation');
    $this->get('/politique-confidentialite')->assertOk()->assertSee('Cookies et stockage');
    $this->get('/')->assertOk()->assertSee('twitter:card')->assertSee('social-preview.png')->assertSee('cookie-settings')->assertSee(route('terms'), false);
    $this->get('/sitemap.xml')->assertOk()->assertSee('/cgu');
});

test('missing pages return a custom page and retain status 404', function () {
    $this->get('/page-inexistante')->assertNotFound()->assertSee('Cette page a pris')->assertSee('Retour à l’accueil')->assertSee('noindex,nofollow');
    $this->getJson('/page-inexistante')->assertNotFound()->assertJsonStructure(['message']);
});

test('https enforcement preserves paths queries and request methods', function () {
    config(['security.force_https' => true]);
    $this->get('http://localhost/services?offer=pro')->assertStatus(308)->assertRedirect('https://localhost/services?offer=pro');
    $this->post('http://localhost/contact')->assertStatus(308)->assertRedirect('https://localhost/contact');
    $this->get('https://localhost/services')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('link flooding is rejected without storing or sending a request', function () {
    Mail::fake();
    RateLimiter::clear('inquiry:127.0.0.1');
    Livewire::test(InquiryForm::class)->set('description', str_repeat('https://spam.example ', 6))->call('submit')->assertHasErrors('description');
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('SQL syntax is treated as text in forms and admin search', function () {
    Mail::fake();
    RateLimiter::clear('inquiry:127.0.0.1');
    $payload = "Robert'); DROP TABLE leads; --";
    Livewire::test(InquiryForm::class)
        ->set('firstname', $payload)->set('lastname', 'Test')->set('email', 'test@example.test')
        ->set('project_type', 'Site Pro')->set('description', 'Une demande de site valide et détaillée.')->set('consent', true)
        ->call('submit')->assertHasNoErrors();
    expect(Lead::first()->firstname)->toBe($payload);
    $this->actingAs(codenyrAdmin());
    Livewire::test(Leads::class)->set('search', "' OR 1=1 --")->assertDontSee('test@example.test');
    expect(Lead::count())->toBe(1);
});
