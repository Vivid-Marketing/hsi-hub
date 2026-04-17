<?php

return [
    'token' => env('CRON_SCHEDULE_TOKEN'),
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cron token
    |--------------------------------------------------------------------------
    |
    | Used by public cron entrypoints (Cloudways) to prevent unauthenticated
    | execution. This value is included in config cache, so it will work even
    | when `.env` is not loaded at runtime.
    |
    */
    'token' => env('CRON_TOKEN', ''),
];

