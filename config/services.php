<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Reenvio asincrono a la web "pública" (k9.heforge.cl).
    // Si base_url esta vacia, no se dispatcha el job -> el server remoto no entra en loop.
    'remote_ingest' => [
        'base_url' => rtrim((string) env('REMOTE_INGEST_BASE_URL', ''), '/'),
        'token'    => env('REMOTE_INGEST_TOKEN'),
        'timeout'  => (int) env('REMOTE_INGEST_TIMEOUT', 8),
    ],

];
