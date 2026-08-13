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

    'routing' => [
        'nominatim_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'osrm_url' => env('OSRM_URL', 'https://router.project-osrm.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'GreenPool Trip Management/1.0'),
    ],

    'google_places' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'base_url' => env('GOOGLE_PLACES_URL', 'https://places.googleapis.com/v1'),
        'max_calls_per_day' => (int) env('GOOGLE_PLACES_MAX_CALLS_PER_DAY', 50),
        'max_calls_per_minute' => (int) env('GOOGLE_PLACES_MAX_CALLS_PER_MINUTE', 10),
        'auto_load_featured' => (bool) env('GOOGLE_PLACES_AUTO_LOAD_FEATURED', true),
        'auto_load_details' => (bool) env('GOOGLE_PLACES_AUTO_LOAD_DETAILS', true),
        'auto_load_cards' => (bool) env('GOOGLE_PLACES_AUTO_LOAD_CARDS', true),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],
    
];
