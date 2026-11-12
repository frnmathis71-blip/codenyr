<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\Recaptcha;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        if (SpamGuard::throttled('registration')) {
            SpamGuard::tooManyRequests('inscription');
        }
        $refusal = match (true) {
            ($input['fax'] ?? '') !== '' => 'honeypot',
            SpamGuard::tooFast($input['started'] ?? null) => 'too_fast',
            SpamGuard::suspiciousName((string) ($input['name'] ?? '')) => 'suspicious_name',
            default => null,
        };
        if ($refusal !== null) {
            $this->refuse($refusal);
        }
        $input['name'] = SpamGuard::cleanLine((string) ($input['name'] ?? ''));
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if (SpamGuard::disposableEmail($input['email'])) {
            SpamGuard::reject('inscription', 'disposable_email');
            throw ValidationException::withMessages(['email' => 'Merci d’utiliser une adresse e-mail durable.']);
        }
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();
        // Last check, so the single-use reCAPTCHA token is only spent on an otherwise valid request.
        $captcha = Recaptcha::assess('inscription', $input['recaptcha_token'] ?? null, $input['recaptcha_challenge'] ?? null);
        if ($captcha['verdict'] === Recaptcha::CHALLENGE) {
            SpamGuard::reject('inscription', (string) $captcha['reason'], $captcha['score']);
            session()->flash('recaptcha_challenge', true);
            throw ValidationException::withMessages(['captcha' => 'Une dernière vérification : cochez la case « Je ne suis pas un robot », puis validez à nouveau.']);
        }
        if ($captcha['verdict'] === Recaptcha::REJECT) {
            $this->refuse((string) $captcha['reason'], $captcha['score']);
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    private function refuse(string $reason, ?float $score = null): never
    {
        SpamGuard::reject('inscription', $reason, $score);
        throw ValidationException::withMessages(['email' => 'L’inscription n’a pas pu aboutir. Vérifiez vos informations puis réessayez.']);
    }
}
