<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    */
    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', 'postgres'),
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'osas_db'),
            'username' => env('DB_USERNAME', 'osas_user'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
            'options'  => [
                // Under php-fpm, ATTR_PERSISTENT=true often amplifies connection churn / odd pgsql+pdo
                // failures under concurrent POS — prefer false + PgBouncer if you need pooling.
                PDO::ATTR_PERSISTENT => filter_var(
                    (string) env('DB_PERSISTENT', 'false'),
                    FILTER_VALIDATE_BOOL
                ),
                PDO::ATTR_TIMEOUT    => 5,
                // Prepared statement caching
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */
    'migrations' => 'migrations',

    /*
    |--------------------------------------------------------------------------
    | Redis Databases — Separated by purpose for isolation + monitoring
    |--------------------------------------------------------------------------
    */
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        // ── Global options ─────────────────────────────────────────────────
        'options' => [
            'cluster'    => env('REDIS_CLUSTER', 'redis'),
            'prefix'     => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'osas'), '_') . '_'),
            // phpredis serializer: igbinary (fastest) or php fallback
            'serializer' => defined('Redis::SERIALIZER_IGBINARY')
                                ? Redis::SERIALIZER_IGBINARY
                                : Redis::SERIALIZER_PHP,
            // phpredis compression: LZF (fast) or none
            'compression'=> defined('Redis::COMPRESSION_LZF')
                                ? Redis::COMPRESSION_LZF
                                : Redis::COMPRESSION_NONE,
        ],

        // ── DB 0: Sessions ─────────────────────────────────────────────────
        'default' => [
            'scheme'             => 'tcp',
            'host'               => env('REDIS_HOST', 'redis'),
            'port'               => env('REDIS_PORT', 6379),
            'password'           => env('REDIS_PASSWORD', null),
            'database'           => 0,
            'read_timeout'       => 1.0,
            'context'            => [],
            'persistent'         => true,
            'persistent_id'      => 'osas_session',
            'retry_interval'     => 50,
            'timeout'            => 1.0,
        ],

        // ── DB 1: Cache (general) ──────────────────────────────────────────
        'cache' => [
            'scheme'         => 'tcp',
            'host'           => env('REDIS_HOST', 'redis'),
            'port'           => env('REDIS_PORT', 6379),
            'password'       => env('REDIS_PASSWORD', null),
            'database'       => 1,
            'read_timeout'   => 0.5,
            'persistent'     => true,
            'persistent_id'  => 'osas_cache',
            'retry_interval' => 50,
            'timeout'        => 0.5,
        ],

        // ── DB 2: KPI / Dashboard cache ────────────────────────────────────
        'kpi' => [
            'scheme'         => 'tcp',
            'host'           => env('REDIS_HOST', 'redis'),
            'port'           => env('REDIS_PORT', 6379),
            'password'       => env('REDIS_PASSWORD', null),
            'database'       => 2,
            'read_timeout'   => 1.0,
            'persistent'     => true,
            'persistent_id'  => 'osas_kpi',
            'retry_interval' => 50,
            'timeout'        => 1.0,
        ],

        // ── DB 3: Queue ────────────────────────────────────────────────────
        'queue' => [
            'scheme'         => 'tcp',
            'host'           => env('REDIS_HOST', 'redis'),
            'port'           => env('REDIS_PORT', 6379),
            'password'       => env('REDIS_PASSWORD', null),
            'database'       => 3,
            'read_timeout'   => 2.0,
            'persistent'     => true,
            'persistent_id'  => 'osas_queue',
            'retry_interval' => 100,
            'timeout'        => 2.0,
        ],

        // ── DB 4: Rate Limiting ────────────────────────────────────────────
        'ratelimit' => [
            'scheme'         => 'tcp',
            'host'           => env('REDIS_HOST', 'redis'),
            'port'           => env('REDIS_PORT', 6379),
            'password'       => env('REDIS_PASSWORD', null),
            'database'       => 4,
            'read_timeout'   => 0.3,
            'persistent'     => true,
            'persistent_id'  => 'osas_ratelimit',
            'retry_interval' => 25,
            'timeout'        => 0.3,
        ],

    ],

];
