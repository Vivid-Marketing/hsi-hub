<?php

return [
    // Used by /internal/cron/schedule?token=... to run schedule:run.
    'token' => env('CRON_SCHEDULE_TOKEN', ''),
];

