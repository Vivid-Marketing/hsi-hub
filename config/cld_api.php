<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CLD API Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('CLD_API_BASE_URL', 'https://cldapi.hsiplatform.com'),

    /*
    |--------------------------------------------------------------------------
    | CLD API Auth (for Bearer token)
    |--------------------------------------------------------------------------
    */
    'admin_id' => env('CLD_API_ADMIN_ID'),
    'password' => env('CLD_API_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | FeedMe trigger (after sync, optionally run Craft FeedMe feed)
    |--------------------------------------------------------------------------
    */
    'feedme' => [
        'prod_url' => env('CLD_FEEDME_PROD_URL', 'https://hsi.com/index.php/actions/feed-me/feeds/run-task'),
        'passkey' => env('CLD_FEEDME_PASSKEY'),
        'prod_feed_id' => env('CLD_FEEDME_PROD_FEED_ID', 70),
        'timeout_seconds' => (int) env('CLD_FEEDME_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public JSON feeds (for Craft FeedMe to ingest)
    |--------------------------------------------------------------------------
    |
    | If passkey is set, requests must include ?passkey=... to access feeds.
    |
    */
    'feeds' => [
        'passkey' => env('CLD_FEEDS_PASSKEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage paths (relative to storage/app or use absolute)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'course_images' => 'cld-api/course-images',
        'course_images_optimized' => 'cld-api/course-images/optimized',
        'course_images_optimized_small' => 'cld-api/course-images/optimized/small',
    ],

    /*
    |--------------------------------------------------------------------------
    | DigitalOcean Spaces (S3-compatible) for course image CDN
    |--------------------------------------------------------------------------
    */
    'do_spaces' => [
        'key' => env('CLD_DO_SPACES_KEY'),
        'secret' => env('CLD_DO_SPACES_SECRET'),
        'bucket' => env('CLD_DO_SPACES_BUCKET', 'hsiassetstorage'),
        'region' => env('CLD_DO_SPACES_REGION', 'sfo2'),
        'endpoint' => env('CLD_DO_SPACES_ENDPOINT', 'https://sfo2.digitaloceanspaces.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token cache duration (seconds). Token refreshed if older than this.
    |--------------------------------------------------------------------------
    */
    'token_ttl_seconds' => 3601 * 4,

    /*
    |--------------------------------------------------------------------------
    | Courses Manager UI (singles processing)
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'max_singles_ids' => (int) env('CLD_UI_MAX_SINGLES_IDS', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync notifications (Postmark)
    |--------------------------------------------------------------------------
    |
    | When set, sends a summary email after CLD sync when there are failures,
    | FeedMe issues, or an abort. Set CLD_SYNC_NOTIFY_ON_SUCCESS=true to
    | always email even when everything succeeded.
    |
    */
    'notify' => [
        'email' => env('CLD_SYNC_NOTIFY_EMAIL'),
        'on_success' => filter_var(env('CLD_SYNC_NOTIFY_ON_SUCCESS', false), FILTER_VALIDATE_BOOL),
    ],

];
