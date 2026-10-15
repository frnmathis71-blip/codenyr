<?php

use App\Livewire\Admin\Tasks;
use App\Models\AdminTask;
use App\Models\User;
use Livewire\Livewire;

test('only admins can open the to-do list', function () {
    $this->get('/admin/a-faire')->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get('/admin/a-faire')->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/a-faire')->assertOk()
        ->assertSee('Souscrire une domiciliation')->assertSee('Déclarer la micro-entreprise');
});

test('tasks can be added, completed, reopened and deleted', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    Livewire::test(Tasks::class)->set('title', 'Créer la page LinkedIn')->set('category', 'Autre')->call('add')->assertHasNoErrors();
    $task = AdminTask::where('title', 'Créer la page LinkedIn')->firstOrFail();

    Livewire::test(Tasks::class)->call('toggle', $task->id);
    expect($task->refresh()->done_at)->not->toBeNull();
    Livewire::test(Tasks::class)->call('toggle', $task->id);
    expect($task->refresh()->done_at)->toBeNull();
    Livewire::test(Tasks::class)->call('delete', $task->id);
    expect(AdminTask::find($task->id))->toBeNull();
});

test('automatic tasks complete themselves once their condition is met', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $siret = AdminTask::where('auto_check', 'legal_siret')->firstOrFail();
    expect($siret->isDone())->toBeFalse();

    config(['codenyr.legal.siret' => '123 456 789 00012']);
    Livewire::test(Tasks::class);

    expect($siret->refresh()->done_at)->not->toBeNull();
    Livewire::test(Tasks::class)->call('toggle', $siret->id);
    expect($siret->refresh()->done_at)->not->toBeNull();
});
