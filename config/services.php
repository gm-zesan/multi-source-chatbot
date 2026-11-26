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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/auth/facebook/callback'),
        'config_id' => env('FACEBOOK_CONFIG_ID', '1431695102154865'),
        'verify_token' => env('FB_VERIFY_TOKEN'),
        'page_access_token' => env('FB_PAGE_ACCESS_TOKEN'),
    ],

    'whatsapp' => [
        'client_id' => env('WHATSAPP_CLIENT_ID', '1769498847715600'),
        'client_secret' => env('WHATSAPP_CLIENT_SECRET', '6670f37de58ce5cca554a1e2dfe719ee'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', env('FB_VERIFY_TOKEN', 'entrepreneurs_automation_whatsapp_webhook')),
    ],

    'memory_service' => [
        'url' => env('PYTHON_MEMORY_SERVICE_URL', 'http://127.0.0.1:8002'),
        'timeout' => (int) env('MEMORY_SERVICE_TIMEOUT', 3),
    ],

    'analytics' => [
        'enabled' => env('PUBLIC_ANALYTICS_ENABLED'),
        'ga4_id' => env('GA4_MEASUREMENT_ID'),
        'meta_pixel_id' => env('META_PIXEL_ID'),
        'clarity_id' => env('CLARITY_PROJECT_ID'),
    ],

];
