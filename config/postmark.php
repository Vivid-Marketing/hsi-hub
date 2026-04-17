<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Postmark server token
    |--------------------------------------------------------------------------
    */
    'token' => env('POSTMARK_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default From address (must be a verified sender signature in Postmark)
    |--------------------------------------------------------------------------
    */
    'from' => env('POSTMARK_FROM_EMAIL', 'noreply@hsi.com'),

    /*
    |--------------------------------------------------------------------------
    | Message stream (usually "outbound")
    |--------------------------------------------------------------------------
    */
    'message_stream' => env('POSTMARK_MESSAGE_STREAM', 'outbound'),

    /*
    |--------------------------------------------------------------------------
    | Course catalog PDF email (template assets)
    |--------------------------------------------------------------------------
    */
    'course_catalog_logo_url' => env(
        'POSTMARK_COURSE_CATALOG_LOGO_URL',
        'https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/hsi-company-logos/HSI_Logo_Color_RGB_300x300.png'
    ),

    'contact_url' => env('POSTMARK_CONTACT_URL', 'https://hsi.com/contact'),

    'hsi_home_url' => env('POSTMARK_HSI_HOME_URL', 'https://hsi.com'),

];
