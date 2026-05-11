<?php

return [
    // Shared secret used for internal signed requests from hsi.com-craft4.
    'shared_secret' => env('SURVEYS_PDF_SHARED_SECRET'),

    // Expected caller identifier.
    'requested_by' => env('SURVEYS_PDF_REQUESTED_BY', 'hsi-surveys'),

    // Max allowed timestamp skew (seconds).
    'max_skew_seconds' => (int) env('SURVEYS_PDF_MAX_SKEW_SECONDS', 300),

    // Soft bounds for payload processing/logging.
    'max_events' => (int) env('SURVEYS_PDF_MAX_EVENTS', 50),
];

