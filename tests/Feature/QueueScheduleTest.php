<?php

use App\Livewire\InquiryForm;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('drains the email queue every minute from the cron', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command, 'queue:work --stop-when-empty'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('sends the inquiry emails right after the response without a queue worker', function () {
    config(['queue.default' => 'database']);
    Livewire::test(InquiryForm::class)
        ->set('firstname', 'Camille')->set('lastname', 'Test')->set('email', 'camille@example.test')
        ->set('project_type', 'Site Vitrine')->set('description', 'Je souhaite présenter mon activité artisanale.')->set('consent', true)
        ->call('submit')->assertHasNoErrors();
    expect(DB::table('jobs')->count())->toBe(2);

    app(DeferredCallbackCollection::class)->invoke();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(app('mail.manager')->mailer()->getSymfonyTransport()->messages())->toHaveCount(2);
});
