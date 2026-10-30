<?php

use App\Livewire\Admin\Outreaches;
use App\Models\Outreach;
use App\Models\User;
use Livewire\Livewire;

test('only admins can open the canvassing list', function () {
    $this->get('/admin/demarchage')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/demarchage')->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/demarchage')->assertOk()
        ->assertSee('Démarchage')->assertSee('Céline David (potière)')->assertSee('tel:0358779203', false);
});

test('businesses can be added, updated and deleted', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Outreaches::class)->call('create')
        ->set('name', 'Boulangerie Test')->set('phone', '03 85 00 00 00')->set('offer', 'landing')
        ->call('save')->assertHasNoErrors();
    $outreach = Outreach::where('name', 'Boulangerie Test')->firstOrFail();
    expect($outreach->status)->toBe('todo')->and($outreach->activity)->toBeNull();

    Livewire::test(Outreaches::class)->call('edit', $outreach->id)
        ->set('status', 'callback')->set('next_action_on', today()->format('Y-m-d'))
        ->call('save')->assertHasNoErrors();
    expect($outreach->refresh()->status)->toBe('callback')
        ->and($outreach->last_contact_on->isToday())->toBeTrue()
        ->and($outreach->isDue())->toBeTrue();

    Livewire::test(Outreaches::class)->call('delete', $outreach->id);
    expect(Outreach::find($outreach->id))->toBeNull();
});

test('the status can be changed straight from the list', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $outreach = Outreach::create(['name' => 'Fleuriste Test', 'status' => 'todo']);

    Livewire::test(Outreaches::class)->call('setStatus', $outreach->id, 'meeting');
    expect($outreach->refresh()->status)->toBe('meeting')->and($outreach->last_contact_on->isToday())->toBeTrue();

    Livewire::test(Outreaches::class)->call('setStatus', $outreach->id, 'nope')->assertStatus(422);
    expect($outreach->refresh()->status)->toBe('meeting');
});

test('invalid offers and statuses are rejected', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Outreaches::class)->call('create')->set('name', 'X')->set('offer', 'nope')->set('status', 'nope')
        ->call('save')->assertHasErrors(['offer', 'status']);
});

test('follow-ups due today or late are counted, closed businesses are not', function () {
    Outreach::query()->delete();
    Outreach::create(['name' => 'En retard', 'status' => 'no_answer', 'next_action_on' => today()->subDay()]);
    Outreach::create(['name' => 'Plus tard', 'status' => 'callback', 'next_action_on' => today()->addDay()]);
    Outreach::create(['name' => 'Refusé', 'status' => 'refused', 'next_action_on' => today()->subDay()]);

    expect(Outreach::due()->pluck('name')->all())->toBe(['En retard']);
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::withQueryParams(['filter' => 'due'])->test(Outreaches::class)
        ->assertSee('En retard')->assertDontSee('Plus tard');
});

test('stale canvassing records are purged unless they became clients', function () {
    Outreach::query()->delete();
    $stale = Outreach::create(['name' => 'Ancien', 'status' => 'refused']);
    $won = Outreach::create(['name' => 'Client', 'status' => 'won']);
    Outreach::whereKey([$stale->id, $won->id])->update(['updated_at' => now()->subYears(4)]);
    $recent = Outreach::create(['name' => 'Récent', 'status' => 'todo']);

    $this->artisan('codenyr:purge-leads')->expectsOutput('1 fiche(s) de démarchage supprimée(s).')->assertSuccessful();
    expect(Outreach::pluck('id')->sort()->values()->all())->toBe([$won->id, $recent->id]);
});
