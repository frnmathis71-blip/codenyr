<?php

use App\Livewire\CustomerReviews;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Livewire\Livewire;

test('verification is disabled and profile has no resend prompt', function () {
    expect(Features::enabled(Features::emailVerification()))->toBeFalse();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/email/verify')->assertNotFound();
    $this->get(route('profile.edit'))->assertOk()->assertDontSee(__('Your email address is unverified.'));
    $this->get(route('appearance.edit'))->assertOk();
    Notification::fake();
    Livewire::test('pages::settings.profile')->call('resendVerificationNotification')->assertHasNoErrors();
    $user->sendEmailVerificationNotification();
    Notification::assertNothingSent();
    expect($user->fresh()->email_verified_at)->toBeNull();
});

test('an unverified linked customer can submit only a moderated review', function () {
    $user = User::factory()->unverified()->create();
    $review = submitCustomerReview($user, deliveredProject($user));
    expect($review->published)->toBeFalse()->and($review->moderation_status)->toBe('pending');
});

test('verification guards can be enabled again without changing user verification records', function () {
    config(['fortify.features' => [...config('fortify.features'), Features::emailVerification()]]);
    $user = User::factory()->unverified()->create();
    Livewire::actingAs($user)->test(CustomerReviews::class)->assertForbidden();
    $this->actingAs($user)->getJson('/espace-client')->assertForbidden();
});
