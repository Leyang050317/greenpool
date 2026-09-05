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

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        // This must be a browser-restricted key. Keep the server Routes/Places
        // key separate because this value is intentionally sent to the browser.
        'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
        'places_base_url' => env('GOOGLE_PLACES_URL', 'https://places.googleapis.com/v1'),
        'routes_base_url' => env('GOOGLE_ROUTES_URL', 'https://routes.googleapis.com/directions/v2'),
    ],

    'google_places' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'base_url' => env('GOOGLE_PLACES_URL', 'https://places.googleapis.com/v1'),
        // Generous for the final demonstration period, while still protecting
        // the shared Google key from accidental request loops.
        'max_calls_per_day' => (int) env('GOOGLE_PLACES_MAX_CALLS_PER_DAY', 500),
        'max_calls_per_minute' => (int) env('GOOGLE_PLACES_MAX_CALLS_PER_MINUTE', 60),
        'auto_load_featured' => (bool) env('GOOGLE_PLACES_AUTO_LOAD_FEATURED', true),
        'auto_load_details' => (bool) env('GOOGLE_PLACES_AUTO_LOAD_DETAILS', true),
        'auto_load_cards' => (bool) env('GOOGLE_PLACES_AUTO_LOAD_CARDS', true),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'gemini' => [
        'enabled' => env('GEMINI_ENABLED', false),
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

];
