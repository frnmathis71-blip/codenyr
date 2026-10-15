<?php

namespace App\Support;

/**
 * Local test mode: lets documents be issued without the legal information a real
 * business needs (SIRET, mediator, complete clauses…). Never active in production
 * unless CODENYR_TEST_MODE is set explicitly; PDFs are stamped as test documents.
 */
class TestMode
{
    public static function enabled(): bool
    {
        return (bool) config('codenyr.test_mode') && ! app()->environment('production');
    }
}
