<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Standalone configuration for direct Typesense HTTP communication.
    | Used by App\Services\Search\TypesenseService.
    |
    | All values are sourced from environment variables — no hardcoded secrets.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    */
    'api_key'  => env('TYPESENSE_API_KEY', ''),
    'host'     => env('TYPESENSE_HOST', 'localhost'),
    'port'     => env('TYPESENSE_PORT', '8108'),
    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
    'path'     => env('TYPESENSE_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Client Behaviour
    |--------------------------------------------------------------------------
    */
    'connection_timeout_seconds' => (int) env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
    'healthcheck_interval_seconds' => (int) env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
    'num_retries' => (int) env('TYPESENSE_NUM_RETRIES', 3),
    'retry_interval_seconds' => (int) env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),

    /*
    |--------------------------------------------------------------------------
    | Collection Prefix
    |--------------------------------------------------------------------------
    |
    | Optional prefix applied to all collection names.
    | Useful for multi-tenant or environment separation.
    |
    */
    'collection_prefix' => env('TYPESENSE_COLLECTION_PREFIX', ''),

];
