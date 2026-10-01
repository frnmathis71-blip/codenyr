<?php

use App\Livewire\Admin\PrivacyRequests;
use App\Livewire\Admin\Testimonials;
use App\Livewire\CustomerReviews;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Lead;
use App\Models\PrivacyRequest;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('privacy requests are scoped have a deadline and an administrative response', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($owner);
    $component = Livewire::test(CustomerReviews::class)->set('privacyType', 'access')->set('privacyMessage', 'Je souhaite une copie de mes données.')->call('requestPrivacy')->assertHasNoErrors();
    $request = PrivacyRequest::sole();
    expect($request->user_id)->toBe($owner->id)->and($request->email)->toBe($owner->email)
        ->and($request->due_at->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString());
    $component->call('requestPrivacy')->assertHasErrors('privacyType');
    expect(PrivacyRequest::count())->toBe(1);
    $this->actingAs($other)->get('/espace-client')->assertDontSee('Je souhaite une copie');
    $this->get('/admin/demandes-rgpd')->assertForbidden();
    Livewire::test(PrivacyRequests::class)->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(PrivacyRequests::class)->call('resolve', $request->id)->assertHasErrors('responses.'.$request->id)
        ->set('responses.'.$request->id, 'La copie demandée a été remise par le canal convenu avec vous.')->call('resolve', $request->id)->assertHasNoErrors();
    expect($request->refresh()->resolved_at)->not->toBeNull();
    $this->actingAs($owner)->get('/espace-client')->assertSee('La copie demandée a été remise');
    $this->actingAs($other)->get('/espace-client')->assertDontSee('La copie demandée a été remise');
});

test('review consent is recorded and withdrawal prevents republication even after reassignment', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $lead = Lead::create(['firstname' => 'Client', 'lastname' => 'Test', 'email' => $owner->email, 'project_type' => 'Site', 'description' => 'Projet de test', 'user_id' => $owner->id, 'status' => 'accepted', 'delivered_at' => now()]);
    $this->actingAs($owner);
    Livewire::test(CustomerReviews::class)->call('edit', $lead->id)->set('content', 'Un accompagnement très clair et très agréable.')->set('consent', true)->call('submit')->assertHasNoErrors();
    $review = Testimonial::sole();
    expect($review->consented_at)->not->toBeNull()->and($review->consent_version)->toBe('review-publication-2026-10-01');
    $review->update(['published' => true, 'moderation_status' => 'approved']);
    $lead->update(['user_id' => $other->id]);
    Livewire::test(CustomerReviews::class)->call('withdrawReview', $review->id)->assertHasNoErrors();
    expect($review->refresh()->published)->toBeFalse()->and($review->withdrawn_at)->not->toBeNull();
    $this->get('/avis')->assertDontSee($review->content);
    $this->actingAs($other);
    Livewire::test(CustomerReviews::class)->call('withdrawReview', $review->id)->assertNotFound();
    $lead->update(['user_id' => $owner->id]);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Testimonials::class)->call('edit', $review->id)->call('moderate', 'approved')->assertHasErrors('moderation_note');
});

test('account deletion revokes sessions document sharing and resets without deleting accounting records', function () {
    $owner = User::factory()->create();
    $client = Client::create(['name' => 'Entreprise', 'email' => $owner->email]);
    $project = ClientProject::create(['client_id' => $client->id, 'user_id' => $owner->id, 'name' => 'Projet', 'type' => 'Site vitrine', 'status' => 'completed']);
    $document = Document::create(['client_id' => $client->id, 'client_project_id' => $project->id, 'name' => 'Pièce comptable', 'type' => 'invoice', 'document_date' => today(), 'client_visible' => true]);
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('password_reset_tokens')->insert(['email' => $owner->email, 'token' => 'test-token', 'created_at' => now()]);
    $this->actingAs($owner);
    Livewire::test('pages::settings.delete-user-modal')->set('password', 'password')->call('deleteUser')->assertHasNoErrors()->assertRedirect('/');
    expect(User::find($owner->id))->toBeNull()->and(DB::table('sessions')->where('user_id', $owner->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $owner->email)->count())->toBe(0)
        ->and($document->refresh()->client_visible)->toBeFalse()->and($project->refresh()->user_id)->toBeNull()->and(Client::find($client->id))->not->toBeNull();
});
