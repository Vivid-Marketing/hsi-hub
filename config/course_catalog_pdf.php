<?php

return [
    // Used for internal signed requests (legacy COURSE_LIBRARY_PDF_SHARED_SECRET).
    'shared_secret' => env('COURSE_LIBRARY_PDF_SHARED_SECRET'),

    // Optional CORS allowlist for browser-based callers.
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('COURSE_CATALOG_PDF_ALLOWED_ORIGINS', '')))),

    // Where generated PDFs are uploaded in DigitalOcean Spaces.
    'do_spaces' => [
        'key' => env('COURSE_CATALOG_PDF_DO_SPACES_KEY', env('CLD_DO_SPACES_KEY')),
        'secret' => env('COURSE_CATALOG_PDF_DO_SPACES_SECRET', env('CLD_DO_SPACES_SECRET')),
        'bucket' => env('COURSE_CATALOG_PDF_DO_SPACES_BUCKET', env('CLD_DO_SPACES_BUCKET', 'hsiassetstorage')),
        'region' => env('COURSE_CATALOG_PDF_DO_SPACES_REGION', env('CLD_DO_SPACES_REGION', 'sfo2')),
        'endpoint' => env('COURSE_CATALOG_PDF_DO_SPACES_ENDPOINT', env('CLD_DO_SPACES_ENDPOINT', 'https://sfo2.digitaloceanspaces.com')),
        'base_path' => env('COURSE_CATALOG_PDF_DO_SPACES_BASE_PATH', 'reports/courses/pdf'),
    ],

    // Local storage (keeps a copy of the generated PDF + HTML for debugging).
    'local_debug' => [
        'enabled' => env('COURSE_CATALOG_PDF_LOCAL_DEBUG', true),
        'pdf_path' => env('COURSE_CATALOG_PDF_LOCAL_PDF_PATH', storage_path('app/reports/courses/pdf')),
        'html_path' => env('COURSE_CATALOG_PDF_LOCAL_HTML_PATH', storage_path('app/reports/courses/html')),
    ],
];

