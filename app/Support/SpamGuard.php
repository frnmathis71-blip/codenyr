<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

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
}
