<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Admin assistants: "anthropic" (Claude, paid API) or "gemini" (Google, free tier available).
    'assistant' => [
        'provider' => env('CODENYR_ASSISTANT_PROVIDER', 'anthropic'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('CODENYR_GEMINI_MODEL', 'gemini-flash-latest'),
        // Free tier: no cost. Fill in the paid rates ($ per million tokens) when billing is enabled.
        'prices' => ['input' => 0.0, 'output' => 0.0, 'cache_read' => 0.0, 'cache_write' => 0.0],
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Assistant administratif : modèle, effort et tarifs ($ par million de tokens) pour l’estimation du coût affichée.
        'model' => env('CODENYR_ASSISTANT_MODEL', 'claude-opus-5-5'),
        'effort' => env('CODENYR_ASSISTANT_EFFORT', 'medium'),
        'prices' => ['input' => 4.0, 'output' => 20.0, 'cache_read' => 0.2, 'cache_write' => 5.0],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
