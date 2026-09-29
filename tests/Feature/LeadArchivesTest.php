<?php

use App\Livewire\Admin\Leads;
use App\Livewire\CustomerReviews;
use App\Models\Lead;
use App\Models\Testimonial;
use App\Models\User;
use Livewire\Livewire;

test('completed sites can be archived and restored without losing their details or reviews', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $client = User::factory()->create();
    $lead = Lead::create(['firstname' => 'ArchivedClient', 'lastname' => 'Test', 'email' => $client->email, 'project_type' => 'Site Pro', 'description' => 'Un site terminé.', 'status' => 'accepted', 'delivered_at' => now(), 'user_id' => $client->id, 'notes' => 'Notes à conserver.']);
    $review = Testimonial::create(['client_name' => $client->name, 'content' => 'Un avis client à conserver.', 'rating' => 5, 'lead_id' => $lead->id, 'user_id' => $client->id, 'published' => true, 'moderation_status' => 'approved']);

    $this->actingAs($admin);
    Livewire::test(Leads::class)->call('archive', $lead->id)->assertHasNoErrors()->assertDontSee('ArchivedClient');
    expect($lead->fresh()->archived_at)->not->toBeNull();
    expect($lead->fresh()->notes)->toBe('Notes à conserver.');
    expect($review->fresh()->published)->toBeTrue();
    $this->get('/admin')->assertOk()->assertDontSee('ArchivedClient')->assertViewHas('counts', fn ($counts) => $counts['accepted'] === 0);
    $this->get('/admin/archives')->assertOk()->assertSee('ArchivedClient');

    Livewire::test(Leads::class, ['archived' => true])->set('search', 'ArchivedClient')->call('open', $lead->id)->assertSee('Notes à conserver.')->call('save')->assertHasErrors('archive');
    $this->actingAs($client);
    Livewire::test(CustomerReviews::class)->call('edit', $lead->id)->assertSet('selected', $lead->id);

    $this->actingAs($admin);
    Livewire::test(Leads::class, ['archived' => true])->call('restore', $lead->id)->assertHasNoErrors()->assertDontSee('ArchivedClient');
    expect($lead->fresh()->archived_at)->toBeNull();
    Livewire::test(Leads::class)->assertSee('ArchivedClient');
});

test('unfinished sites cannot be archived', function (string $status, bool $delivered) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $lead = Lead::create(['firstname' => 'Camille', 'lastname' => 'Test', 'email' => 'test@example.test', 'project_type' => 'Site Pro', 'description' => 'Projet en cours.', 'status' => $status, 'delivered_at' => $delivered ? now() : null]);

    Livewire::test(Leads::class)->call('archive', $lead->id)->assertHasErrors('archive');
    expect($lead->fresh()->archived_at)->toBeNull();
})->with([['accepted', false], ['new', false], ['quote_sent', true]]);
