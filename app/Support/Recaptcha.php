<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side check of Google reCAPTCHA tokens. Each form sends a v3 token (invisible, scored);
 * a doubtful score is answered with a v2 checkbox challenge rather than a refusal.
 */
class Recaptcha
{
    public const PASS = 'pass';

    public const CHALLENGE = 'challenge';

    public const REJECT = 'reject';

    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public static function enabled(): bool
    {
        return filled(config('services.recaptcha.site_key')) && filled(config('services.recaptcha.secret_key'));
    }

    public static function challengeAvailable(): bool
    {
        return self::enabled() && filled(config('services.recaptcha.v2_site_key')) && filled(config('services.recaptcha.v2_secret_key'));
    }

    /**
     * @param  string  $action  The action the form declared when asking for its token (one per form).
     * @param  string|null  $token  reCAPTCHA v3 token.
     * @param  string|null  $challengeToken  reCAPTCHA v2 token, once the visitor ticked the checkbox.
     * @return array{verdict: self::PASS|self::CHALLENGE|self::REJECT, reason: string|null, score: float|null}
     */
    public static function assess(string $action, ?string $token, ?string $challengeToken = null): array
    {
        if (! self::enabled()) {
            return self::result(self::PASS);
        }
        if (filled($challengeToken) && self::challengeAvailable()) {
            $answer = self::verify((string) config('services.recaptcha.v2_secret_key'), (string) $challengeToken);
            if ($answer === null) {
                return self::result(self::PASS, 'captcha_unavailable');
            }

            return ($answer['success'] ?? false) === true ? self::result(self::PASS) : self::result(self::REJECT, 'captcha_challenge_failed');
        }
        if (blank($token)) {
            return self::result(self::REJECT, 'captcha_missing');
        }
        $answer = self::verify((string) config('services.recaptcha.secret_key'), (string) $token);
        if ($answer === null) {
            // Google cannot be reached: the form stays usable, the other protections still apply.
            return self::result(self::PASS, 'captcha_unavailable');
        }
        $score = isset($answer['score']) && is_numeric($answer['score']) ? (float) $answer['score'] : null;
        if (($answer['success'] ?? false) !== true) {
            $expired = in_array('timeout-or-duplicate', (array) ($answer['error-codes'] ?? []), true);

            return self::result(self::REJECT, $expired ? 'captcha_expired' : 'captcha_invalid', $score);
        }
        if (($answer['action'] ?? null) !== $action) {
            return self::result(self::REJECT, 'captcha_action_mismatch', $score);
        }
        if ($score === null) {
            return self::result(self::REJECT, 'captcha_invalid');
        }
        if ($score >= (float) config('services.recaptcha.threshold')) {
            return self::result(self::PASS, null, $score);
        }
        if ($score >= (float) config('services.recaptcha.challenge_threshold') && self::challengeAvailable()) {
            return self::result(self::CHALLENGE, 'captcha_challenge_required', $score);
        }

        return self::result(self::REJECT, 'captcha_low_score', $score);
    }

    /** @return array<string, mixed>|null Google's answer, or null when the service did not answer. */
    private static function verify(string $secret, string $token): ?array
    {
        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, ['secret' => $secret, 'response' => $token]);
        } catch (ConnectionException $exception) {
            Log::warning('reCAPTCHA injoignable : '.$exception->getMessage());

            return null;
        }
        if (! $response->successful() || ! is_array($response->json())) {
            Log::warning('reCAPTCHA : réponse inattendue (HTTP '.$response->status().').');

            return null;
        }

        return $response->json();
    }

    /** @return array{verdict: self::PASS|self::CHALLENGE|self::REJECT, reason: string|null, score: float|null} */
    private static function result(string $verdict, ?string $reason = null, ?float $score = null): array
    {
        return ['verdict' => $verdict, 'reason' => $reason, 'score' => $score];
    }
}
