<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Cheap checks that stop automated submissions (scripts, AI agents) without bothering real visitors:
 * a form sent faster than a human can type, or from a throwaway mailbox.
 */
class SpamGuard
{
    /** Encrypted timestamp to put in a hidden field when the form is displayed. */
    public static function startToken(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    /** True when the form came back sooner than a person could fill it, or without a valid token. */
    public static function tooFast(int|string|null $startedAt): bool
    {
        $min = (int) config('codenyr.spam.min_seconds');
        if ($min <= 0) {
            return false;
        }
        if (is_string($startedAt)) {
            try {
                $startedAt = (int) Crypt::decryptString($startedAt);
            } catch (DecryptException) {
                return true;
            }
        }

        return ! $startedAt || now()->timestamp - $startedAt < $min;
    }

    public static function disposableEmail(string $email): bool
    {
        $domain = mb_strtolower(trim(substr(strrchr($email, '@') ?: '', 1)));

        return in_array($domain, config('codenyr.spam.disposable_domains'), true);
    }

    /** Names never contain links or markup; bots often stuff them there. */
    public static function suspiciousName(string $value): bool
    {
        return (bool) preg_match('~https?://|www\.|<[a-z/]|\.(com|ru|xyz|top)\b~i', $value);
    }

    /** Shown for every refusal, so the rule that blocked is never revealed. */
    public static function message(): string
    {
        return 'Votre demande n’a pas pu être envoyée, merci de réessayer. Si le problème persiste, écrivez-nous à '.config('codenyr.email').'.';
    }

    /** Counts one submission for this visitor; true once the form's quota per 10 minutes is used up. */
    public static function throttled(string $form): bool
    {
        $key = $form.':'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, max(1, (int) config('codenyr.spam.max_attempts')))) {
            return true;
        }
        RateLimiter::hit($key, 600);

        return false;
    }

    /** Refuses the request with HTTP 429 and records why. */
    public static function tooManyRequests(string $form, string $reason = 'rate_limit'): never
    {
        self::reject($form, $reason);
        abort(429, 'Trop de tentatives. Merci de réessayer dans quelques minutes.');
    }

    /** Journal of refused submissions (storage/logs/spam-*.log), used to tune the thresholds. */
    public static function reject(string $form, string $reason, ?float $score = null): void
    {
        Log::channel('spam')->info('Soumission rejetée', [
            'form' => $form,
            'reason' => $reason,
            'score' => $score,
            'ip' => IpUtils::anonymize((string) request()->ip()),
        ]);
    }

    public static function tooManyLinks(string $text): bool
    {
        return preg_match_all('~https?://|\bwww\.~i', $text) > (int) config('codenyr.spam.max_links');
    }

    public static function spamKeyword(string ...$texts): bool
    {
        $text = mb_strtolower(implode("\n", $texts));
        foreach ((array) config('codenyr.spam.keywords') as $keyword) {
            if ($keyword !== '' && str_contains($text, mb_strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }

    /** One-line field: no markup, no control character (a line break could forge an e-mail header). */
    public static function cleanLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', strip_tags($value))));
    }

    /** Free text: no markup, line breaks kept. */
    public static function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", strip_tags($value));

        return trim((string) preg_replace('/[^\P{Cc}\n\t]+/u', '', $value));
    }
}
