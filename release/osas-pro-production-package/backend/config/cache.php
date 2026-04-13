<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    */
    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */
    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table'  => 'cache',
            'connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache/data'),
        ],

        // ── Primary Redis cache — fast, short-lived ────────────────────────
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        // ── KPI / Dashboard cache — longer TTL ────────────────────────────
        'kpi' => [
            'driver'     => 'redis',
            'connection' => 'kpi',
            'lock_connection' => 'default',
        ],

        // ── Rate-limiter cache ─────────────────────────────────────────────
        'ratelimit' => [
            'driver'     => 'redis',
            'connection' => 'ratelimit',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('CACHE_PREFIX', 'osas_'),

];
