<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest Endpoint
    |--------------------------------------------------------------------------
    |
    | The CodeBros portal endpoint that receives batched telemetry, and the
    | per-app bearer token issued for this application on the portal.
    |
    */

    'endpoint' => env('MONITORING_ENDPOINT'),

    'token' => env('MONITORING_TOKEN'),

    'timeout' => env('MONITORING_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Buffer
    |--------------------------------------------------------------------------
    |
    | Recorded entries are buffered in the cache between requests/jobs and
    | flushed to the portal in batches by `php artisan pulse:work`. Set
    | `cache_store` to a specific store name, or leave null to use the
    | application's default cache store.
    |
    */

    'cache_store' => env('MONITORING_CACHE_STORE'),

    'chunk' => env('MONITORING_CHUNK', 500),

];
