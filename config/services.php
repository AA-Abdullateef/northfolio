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

    // USD market price providers, used by AssetPriceService to convert
    // per-asset balances into a total portfolio value.
    'coingecko' => [
        'key' => env('COINGECKO_API_KEY'),
        'require_key' => env('COINGECKO_REQUIRE_KEY', false),
    ],

    'alphavantage' => [
        'key' => env('ALPHAVANTAGE_API_KEY'),
    ],

    'finnhub' => [
        'key' => env('FINNHUB_API_KEY'),
    ],

    // Shared secret for the unauthenticated GET admin/assets/sync-prices/run
    // route, so an external cron/uptime service can trigger a price sync
    // without an interactive admin session. Set a long random value in .env.
    'price_sync' => [
        'key' => env('PRICE_SYNC_SECRET'),
    ],


    'stripe' => [
        'secret'         => env('STRIPE_SECRET_KEY'),
        'publishable'    => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];