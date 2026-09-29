<?php

use App\Livewire\Admin\Leads;
use App\Livewire\Admin\Projects;
use App\Livewire\Admin\Testimonials;
use App\Livewire\InquiryForm;
use App\Mail\InquiryReceived;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function codenyrAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_admin' => true])->save();

    return $user;
}

function projectData(): array
{
    return ['name' => 'Projet test', 'slug' => 'projet-test', 'client' => 'Client test', 'category' => 'Artisanat', 'short_description' => 'Un site de présentation.', 'description' => 'Description du projet.', 'problem' => 'Présenter une activité.', 'solution' => 'Créer un site vitrine.', 'features' => 'Formulaire', 'website_url' => '', 'technologies' => 'Laravel, Livewire', 'published' => false, 'featured' => false, 'is_demo' => true];
}

beforeEach(function () {
    RateLimiter::clear('inquiry:127.0.0.1');
});

test('public pages render and include a single main heading', function (string $path) {
    $response = $this->get($path)->assertOk();
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
})->with(['/', '/services', '/tarifs', '/realisations', '/a-propos', '/devis', '/contact', '/mentions-legales', '/politique-confidentialite', '/login']);

test('customer registration is available and validates input', function () {
    $this->get('/register')->assertOk()->assertSee('Créer mon compte client');
    $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password']);
});

test('administration requires an authorized account', function (string $path) {
    $this->get($path)->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
    $this->actingAs(codenyrAdmin())->get($path)->assertOk();
})->with(['/admin', '/admin/prospects', '/admin/archives', '/admin/realisations', '/admin/temoignages']);

test('livewire administration independently enforces authorization', function (string $component) {
    $this->actingAs(User::factory()->create());
    Livewire::test($component)->assertForbidden();
})->with([Leads::class, Projects::class, Testimonials::class]);

test('quote validates input before creating a lead', function () {
    Mail::fake();
    Livewire::test(InquiryForm::class)->set('email', 'bad-email')->call('submit')->assertHasErrors(['firstname', 'lastname', 'email', 'project_type', 'description', 'consent']);
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('quote creates exactly one lead and queues both messages', function () {
    Mail::fake();
    Livewire::test(InquiryForm::class)
        ->set('firstname', 'Camille')->set('lastname', 'Test')->set('email', 'camille@example.test')
        ->set('project_type', 'Site Vitrine')->set('budget', '500–1 000 €')->set('features', ['Formulaire'])
        ->set('description', 'Je souhaite présenter mon activité artisanale.')->set('consent', true)
        ->call('submit')->assertHasNoErrors()->assertSet('submitted', true)->assertSee('Votre projet commence ici.')
        ->call('submit');
    expect(Lead::count())->toBe(1);
    expect(Lead::first()->status)->toBe('new');
    expect(Lead::first()->features)->toBeNull();
    expect(Lead::first()->budget)->toBeNull();
    Mail::assertQueued(InquiryReceived::class, fn ($mail) => $mail->confirmation && $mail->hasTo('camille@example.test'));
    Mail::assertQueued(InquiryReceived::class, fn ($mail) => ! $mail->confirmation && $mail->hasTo(config('codenyr.email')));
    Mail::assertQueuedCount(2);
});

test('contact stores its subject and does not require an offer', function () {
    Mail::fake();
    Livewire::test(InquiryForm::class, ['mode' => 'contact'])->set('firstname', 'Jean')->set('lastname', 'Test')->set('email', 'jean@example.test')->set('subject', 'Un premier échange')->set('description', 'Je souhaite échanger au sujet de mon nouveau site.')->set('consent', true)->call('submit')->assertHasNoErrors();
    expect(Lead::first()->project_type)->toBe('Contact');
    expect(Lead::first()->description)->toContain('Un premier échange');
});

test('spam and excessive submissions are rejected', function () {
    Mail::fake();
    Livewire::test(InquiryForm::class)->set('fax', 'spam')->call('submit')->assertHasErrors('submit');
    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit('inquiry:127.0.0.1', 600);
    }
    Livewire::test(InquiryForm::class)->call('submit')->assertHasErrors('submit');
    expect(Lead::count())->toBe(0);
    Mail::assertNothingQueued();
});

test('unsafe website is rejected', function () {
    Livewire::test(InquiryForm::class)->set('website', 'javascript:alert(1)')->call('submit')->assertHasErrors(['website']);
});

test('inquiries explain offers without budget or additional feature inputs', function (string $path) {
    $response = $this->get($path)->assertOk()->assertDontSee('Budget envisagé')->assertDontSee('Les fonctionnalités envisagées')->assertDontSee('wire:model="features"', false)->assertDontSee('wire:model="budget"', false);
    if ($path === '/devis') {
        $response->assertSee(config('codenyr.offers.vitrine.description'))->assertSee('Jusqu’à 5 pages personnalisées')->assertSee('Ce site comprend');
    }
})->with(['/devis', '/contact']);

test('quote can preselect an offer and stores only that offer', function () {
    Mail::fake();
    Livewire::withQueryParams(['offer' => 'Site Vitrine'])->test(InquiryForm::class)
        ->assertSet('project_type', 'Site Vitrine')
        ->set('firstname', 'Camille')->set('lastname', 'Test')->set('email', 'camille@example.test')
        ->set('budget', 'Injected budget')->set('features', ['Injected feature'])
        ->set('description', 'Je souhaite présenter mon activité artisanale.')->set('consent', true)
        ->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
    expect(Lead::first()->project_type)->toBe('Site Vitrine');
    expect(Lead::first()->budget)->toBeNull();
    expect(Lead::first()->features)->toBeNull();
});

test('password visibility controls render a single icon', function () {
    $html = $this->get('/login')->assertOk()->getContent();
    expect(substr_count($html, 'data-password-visibility-icon'))->toBe(1);
    expect($html)->not->toContain('[[data-viewable-open]');
});

test('unpublished projects never appear publicly or in the sitemap', function () {
    $data = projectData();
    $data['technologies'] = ['Laravel'];
    $project = Project::create($data);
    $this->get('/realisations/projet-test')->assertNotFound();
    $this->get('/realisations')->assertDontSee('Projet test');
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('projet-test');
    $project->update(['published' => true]);
    $this->get('/realisations/projet-test')->assertOk()->assertSee('Projet test')->assertSee('Concept de démonstration');
    $this->get('/realisations')->assertSee('Projet test');
    $this->get('/sitemap.xml')->assertSee('projet-test');
});

test('admin can create update and delete a project with a secure image', function () {
    Storage::fake('public');
    $this->actingAs(codenyrAdmin());
    $component = Livewire::test(Projects::class)->call('create')->set('form', projectData())->set('image', UploadedFile::fake()->image('project.jpg', 800, 600))->call('save')->assertHasNoErrors();
    $project = Project::firstOrFail();
    Storage::disk('public')->assertExists($project->image);
    expect($project->technologies)->toBe(['Laravel', 'Livewire']);
    $component->call('edit', $project->id)->set('form.published', true)->set('form.name', 'Projet modifié')->call('save')->assertHasNoErrors();
    expect($project->fresh()->published)->toBeTrue();
    $component->call('delete', $project->id);
    Storage::disk('public')->assertMissing($project->image);
    expect(Project::count())->toBe(0);
});

test('invalid project slug and executable uploads are rejected', function () {
    Storage::fake('public');
    $this->actingAs(codenyrAdmin());
    Livewire::test(Projects::class)->call('create')->set('form', projectData())->set('form.slug', '../invalid')->set('image', UploadedFile::fake()->create('script.php', 1, 'text/plain'))->call('save')->assertHasErrors(['form.slug', 'image']);
    expect(Project::count())->toBe(0);
});

test('prospects can be searched filtered updated and deleted', function () {
    $this->actingAs(codenyrAdmin());
    $lead = Lead::create(['firstname' => 'Camille', 'lastname' => 'Test', 'email' => 'test@example.test', 'project_type' => 'Site Pro', 'description' => 'Un projet test.']);
    $component = Livewire::test(Leads::class)->set('search', 'Camille')->assertSee('Camille')->set('filter', 'accepted')->assertDontSee('test@example.test')->set('filter', '')->call('open', $lead->id)->set('status', 'invalid')->call('save')->assertHasErrors('status');
    $component->set('status', 'quote_sent')->set('notes', 'Proposition envoyée.')->call('save')->assertHasNoErrors();
    expect($lead->fresh()->notes)->toBe('Proposition envoyée.');
    $component->call('delete', $lead->id);
    expect(Lead::count())->toBe(0);
});

test('testimonials support CRUD and published visibility', function () {
    $this->actingAs(codenyrAdmin());
    $component = Livewire::test(Testimonials::class)->call('create')->set('form.client_name', 'Auteur test')->set('form.content', 'Un avis de test, non destiné à la production.')->set('form.rating', 6)->call('save')->assertHasErrors('form.rating')->set('form.rating', 4)->call('save')->assertHasNoErrors();
    $testimonial = Testimonial::firstOrFail();
    $this->get('/')->assertDontSee('Auteur test');
    $component->call('edit', $testimonial->id)->set('form.published', true)->call('save')->assertHasNoErrors();
    $this->get('/')->assertSee('Auteur test');
    $component->call('delete', $testimonial->id);
    expect(Testimonial::count())->toBe(0);
});

test('mail renders both variants with the complete request', function () {
    $lead = Lead::create(['firstname' => 'Camille', 'lastname' => 'Test', 'email' => 'test@example.test', 'project_type' => 'Site Pro', 'description' => 'Une demande avec des informations détaillées.', 'features' => ['Administration']]);
    (new InquiryReceived($lead, true))->assertSeeInHtml('Camille')->assertSeeInHtml('Administration');
    (new InquiryReceived($lead, false))->assertSeeInHtml('test@example.test')->assertSeeInHtml('Consulter les prospects');
});
