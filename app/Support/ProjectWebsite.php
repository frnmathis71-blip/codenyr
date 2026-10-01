<?php

namespace App\Support;

class ProjectWebsite
{
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }
        // Keep explicit schemes intact so validation can reject unsupported ones.
        if (! preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
            return 'https://'.$value;
        }

        return $value;
    }
}
