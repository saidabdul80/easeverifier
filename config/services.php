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

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    'nabteb' => [
        'latest_year' => env('NABTEB_LATEST_YEAR', 2025),
        'connect_timeout' => env('NABTEB_CONNECT_TIMEOUT', 5),
        'timeout' => env('NABTEB_TIMEOUT', 12),
    ],

    'naija_result_pins' => [
        'base_url' => env('NAIJA_RESULT_PINS_BASE_URL', 'https://www.naijaresultpins.com/api/v1'),
        'token' => env('NAIJA_RESULT_PINS_TOKEN'),
        'timeout' => env('NAIJA_RESULT_PINS_TIMEOUT', 45),
    ],

    'neco_everify' => [
        'base_url' => env('NECO_EVERIFY_BASE_URL', 'https://everify.neco.gov.ng/api_core'),
        'bearer_token' => env('NECO_EVERIFY_BEARER_TOKEN'),
        'timeout' => env('NECO_EVERIFY_TIMEOUT', 20),
    ],

];
