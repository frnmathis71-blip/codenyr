<?php

use App\Livewire\Admin\Leads;
use App\Livewire\Admin\Testimonials;
use App\Livewire\CustomerReviews;
use App\Models\Lead;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function deliveredProject(User $user, array $attributes = []): Lead
{
    return Lead::create(array_merge(['firstname' => 'Client', 'lastname' => 'Test', 'email' => $user->email, 'company' => 'Entreprise test', 'project_type' => 'Site Vitrine', 'description' => 'Un projet de site pour tester les avis.', 'user_id' => $user->id, 'status' => 'accepted', 'delivered_at' => now()], $attributes));
}

function submitCustomerReview(User $user, Lead $lead): Testimonial
{
    Livewire::actingAs($user)->test(CustomerReviews::class)->call('edit', $lead->id)->set('content', 'Un accompagnement très clair pour la réalisation de mon site.')->set('rating', 4)->set('consent', true)->call('submit')->assertHasNoErrors();

    return Testimonial::where('lead_id', $lead->id)->firstOrFail();
}

beforeEach(fn () => Cache::flush());

test('registration grants immediate customer access without verification email', function () {
    Notification::fake();
    $this->post('/register', ['name' => 'Client Test', 'email' => 'client@example.test', 'password' => 'Password-Test123!', 'password_confirmation' => 'Password-Test123!', 'is_admin' => true, 'email_verified_at' => now()])->assertSessionHasNoErrors();
    $user = User::where('email', 'client@example.test')->firstOrFail();
    expect($user->is_admin)->toBeFalse()->and($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertNothingSent();
    $this->get('/dashboard')->assertRedirect(route('customer.dashboard'));
    $this->get('/espace-client')->assertOk();
});

test('existing unverified customers can access their space without administrator rights', function () {
    $this->get('/espace-client')->assertRedirect('/login');
    $user = User::factory()->unverified()->create();
    Livewire::actingAs($user)->test(CustomerReviews::class)->assertOk();
    $this->actingAs($user)->get('/espace-client')->assertOk()->assertSee('Aucun projet associé');
    $this->get('/admin')->assertForbidden();
    $this->actingAs(codenyrAdmin())->get('/dashboard')->assertRedirect(route('admin.dashboard'));
});

test('only accepted delivered projects linked to the customer permit reviews', function (string $state) {
    $user = User::factory()->create();
    $lead = deliveredProject($user, match ($state) {
        'not-accepted' => ['status' => 'quote_sent'],
        'not-delivered' => ['delivered_at' => null],
        'unlinked' => ['user_id' => null],
        default => ['user_id' => User::factory()->create()->id],
    });
    Livewire::actingAs($user)->test(CustomerReviews::class)->call('edit', $lead->id)->assertNotFound();
    expect(Testimonial::count())->toBe(0);
})->with(['not-accepted', 'not-delivered', 'unlinked', 'other-customer']);

test('submission validates consent rating and content and never publishes directly', function () {
    $user = User::factory()->create();
    $lead = deliveredProject($user);
    Livewire::actingAs($user)->test(CustomerReviews::class)->call('edit', $lead->id)->set('rating', 6)->call('submit')->assertHasErrors(['rating', 'content', 'consent']);
    $review = submitCustomerReview($user, $lead);
    expect($review->published)->toBeFalse()->and($review->moderation_status)->toBe('pending')->and($review->user_id)->toBe($user->id);
    $this->get('/')->assertDontSee($review->content);
});

test('admin can approve a customer review and editing requires another moderation', function () {
    $user = User::factory()->create();
    $lead = deliveredProject($user);
    $review = submitCustomerReview($user, $lead);
    $admin = codenyrAdmin();
    Livewire::actingAs($admin)->test(Testimonials::class)->call('edit', $review->id)->call('moderate', 'approved')->assertHasNoErrors();
    expect($review->fresh()->published)->toBeTrue();
    $this->get('/')->assertSee($review->content);
    Livewire::actingAs($user)->test(CustomerReviews::class)->call('edit', $lead->id)->set('content', 'Une nouvelle version de mon avis, à faire valider de nouveau.')->set('consent', true)->call('submit')->assertHasNoErrors();
    expect(Testimonial::count())->toBe(1)->and($review->fresh()->published)->toBeFalse()->and($review->fresh()->revision)->toBe(2);
    $this->get('/')->assertDontSee('Une nouvelle version de mon avis');
});

test('admin rejects with an explanation visible only in customer space', function () {
    $user = User::factory()->create();
    $lead = deliveredProject($user);
    $review = submitCustomerReview($user, $lead);
    Livewire::actingAs(codenyrAdmin())->test(Testimonials::class)->call('edit', $review->id)->call('moderate', 'rejected')->assertHasErrors('moderation_note')->set('moderation_note', 'Merci de retirer les coordonnées personnelles de votre texte.')->call('moderate', 'rejected')->assertHasNoErrors();
    expect($review->fresh()->moderation_status)->toBe('rejected')->and($review->fresh()->published)->toBeFalse();
    Livewire::actingAs($user)->test(CustomerReviews::class)->assertSee('Merci de retirer les coordonnées personnelles');
    $this->get('/')->assertDontSee('Merci de retirer les coordonnées personnelles');
});

test('admin cannot approve an unseen revision or edit customer words via manual CRUD', function () {
    $user = User::factory()->create();
    $lead = deliveredProject($user);
    $review = submitCustomerReview($user, $lead);
    $admin = codenyrAdmin();
    $component = Livewire::actingAs($admin)->test(Testimonials::class)->call('edit', $review->id);
    $review->update(['content' => 'Le client vient de changer son avis après ouverture par le modérateur.', 'revision' => 2]);
    $component->call('moderate', 'approved')->assertHasErrors('moderation_note');
    expect($review->fresh()->published)->toBeFalse();
    $component->call('save')->assertForbidden();
});

test('delivery authorization is checked again on submit and moderation', function () {
    $user = User::factory()->create();
    $lead = deliveredProject($user);
    $component = Livewire::actingAs($user)->test(CustomerReviews::class)->call('edit', $lead->id)->set('content', 'Un avis valide mais le projet a été révoqué entre-temps.')->set('consent', true);
    $lead->update(['delivered_at' => null]);
    $component->call('submit')->assertNotFound();
    $lead->update(['delivered_at' => now()]);
    $review = submitCustomerReview($user, $lead);
    $lead->update(['status' => 'refused']);
    Livewire::actingAs(codenyrAdmin())->test(Testimonials::class)->call('edit', $review->id)->call('moderate', 'approved')->assertHasErrors('moderation_note');
    expect($review->fresh()->published)->toBeFalse();
});

test('admin links an unverified client and confirms delivery separately', function () {
    $user = User::factory()->unverified()->create();
    $lead = deliveredProject($user, ['user_id' => null, 'status' => 'new', 'delivered_at' => null]);
    $component = Livewire::actingAs(codenyrAdmin())->test(Leads::class)->call('open', $lead->id)->set('client_email', $user->email)->set('delivered', true)->call('save')->assertHasErrors('delivered');
    $component->set('status', 'accepted')->call('save')->assertHasNoErrors();
    expect($lead->fresh()->user_id)->toBe($user->id)->and($lead->fresh()->delivered_at)->not->toBeNull();
    $component->set('client_email', 'not-registered@example.test')->call('save')->assertHasErrors('client_email');
});

test('revoking delivery unpublishes the customer review and prevents reassignment', function () {
    $user = User::factory()->create();
    $lead = deliveredProject($user);
    $review = submitCustomerReview($user, $lead);
    $admin = codenyrAdmin();
    Livewire::actingAs($admin)->test(Testimonials::class)->call('edit', $review->id)->call('moderate', 'approved');
    $component = Livewire::actingAs($admin)->test(Leads::class)->call('open', $lead->id)->set('delivered', false)->call('save')->assertHasNoErrors();
    expect($review->fresh()->published)->toBeFalse();
    $component->set('client_email', '')->call('save')->assertHasErrors('client_email');
});
