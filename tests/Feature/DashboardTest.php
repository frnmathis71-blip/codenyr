<?php

use App\Livewire\Admin\Leads;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('admin dashboard and prospects render after receiving a project', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $lead = Lead::create(['firstname' => 'Camille', 'lastname' => 'Test', 'email' => 'test@example.test', 'project_type' => 'Site Pro', 'description' => 'Un nouveau projet à consulter.']);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Camille');
    $this->get(route('admin.leads'))->assertOk()->assertSee('Camille');
    Livewire::test(Leads::class)->call('open', $lead->id)->assertSee($lead->description);
});

test('dashboard counts all lead statuses in one aggregate query', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    foreach (array_keys(Lead::STATUSES) as $status) {
        Lead::create(['firstname' => 'Test', 'lastname' => $status, 'email' => 'test@example.test', 'project_type' => 'Site Pro', 'description' => 'Projet test.', 'status' => $status]);
    }
    DB::enableQueryLog();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    expect($response->viewData('counts')->all())->toBe(array_fill_keys(array_keys(Lead::STATUSES), 1));
    expect($queries->filter(fn ($query) => str_contains(strtolower($query['query']), 'count(*)') && str_contains($query['query'], 'leads')))->toHaveCount(1);
});

test('layouts configure the bundled runtime without injecting another copy', function (string $path) {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $response = $this->get($path)->assertOk();

    expect(substr_count($response->getContent(), 'window.livewireScriptConfig ='))->toBe(1);
    $response->assertDontSee('/flux/flux.js', false)->assertDontSee('/livewire.js?id=', false);
})->with(['/', '/admin', '/admin/prospects', '/settings/profile']);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('customers are redirected to their own space', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('customer.dashboard'));
});
