<?php

use App\Mail\InquiryReceived;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

beforeEach(fn () => app()->setLocale('fr'));

it('sends the password reset email in French', function () {
    $mail = (new ResetPassword('token'))->toMail(User::factory()->create());
    $html = (string) $mail->render();

    expect($mail->subject)->toBe('Réinitialisez votre mot de passe')
        ->and($html)->toContain('Bonjour,', 'Réinitialiser le mot de passe', 'Ce lien expire dans', 'Si le bouton', 'Tous droits réservés.')
        ->not->toContain('Hello!', 'Regards,', 'Reset Password', 'All rights reserved.');
});

it('sends the inquiry emails in French', function (bool $confirmation) {
    $lead = Lead::create(['firstname' => 'Camille', 'lastname' => 'Test', 'email' => 'camille@example.test', 'project_type' => 'Site Vitrine', 'description' => 'Je souhaite présenter mon activité artisanale.']);

    expect((new InquiryReceived($lead, $confirmation))->render())->toContain('Tous droits réservés.')->not->toContain('All rights reserved.');
})->with([true, false]);
