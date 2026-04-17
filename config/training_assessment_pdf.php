<?php

return [
    // Optional CORS allowlist for browser-based callers.
    // Comma-separated list of origins, e.g. https://hsi.com,https://staging.hsi.com
    'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('TRAINING_ASSESSMENT_PDF_ALLOWED_ORIGINS', env('COURSE_CATALOG_PDF_ALLOWED_ORIGINS', ''))))),

    // Where generated PDFs are uploaded in DigitalOcean Spaces.
    'do_spaces' => [
        'key' => env('TRAINING_ASSESSMENT_PDF_DO_SPACES_KEY', env('CLD_DO_SPACES_KEY')),
        'secret' => env('TRAINING_ASSESSMENT_PDF_DO_SPACES_SECRET', env('CLD_DO_SPACES_SECRET')),
        'bucket' => env('TRAINING_ASSESSMENT_PDF_DO_SPACES_BUCKET', env('CLD_DO_SPACES_BUCKET', 'hsiassetstorage')),
        'region' => env('TRAINING_ASSESSMENT_PDF_DO_SPACES_REGION', env('CLD_DO_SPACES_REGION', 'sfo2')),
        'endpoint' => env('TRAINING_ASSESSMENT_PDF_DO_SPACES_ENDPOINT', env('CLD_DO_SPACES_ENDPOINT', 'https://sfo2.digitaloceanspaces.com')),
        'base_path' => env('TRAINING_ASSESSMENT_PDF_DO_SPACES_BASE_PATH', 'reports/training-assessments/pdf'),
    ],

    // Local storage (keeps a copy of the generated PDF for debugging).
    'local_debug' => [
        'enabled' => env('TRAINING_ASSESSMENT_PDF_LOCAL_DEBUG', true),
        'pdf_path' => env('TRAINING_ASSESSMENT_PDF_LOCAL_PDF_PATH', storage_path('app/reports/training-assessments/pdf')),
    ],
];

