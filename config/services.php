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
        // Laravel's Postmark mail transport reads from services.postmark.token
        // We standardize on POSTMARK_API_KEY across the app.
        'token' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'vimeo' => [
        // Personal access token (Authenticated, with Public + Private scopes).
        'access_token' => env('VIMEO_ACCESS_TOKEN'),
        'base_url' => env('VIMEO_API_BASE_URL', 'https://api.vimeo.com'),
        // Safety cap on how many videos we'll page through per request.
        'max_videos' => (int) env('VIMEO_MAX_VIDEOS', 500),
    ],

];
