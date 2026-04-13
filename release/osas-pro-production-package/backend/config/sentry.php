<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.2),

    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'breadcrumbs' => [
        'logs'                => true,
        'cache'               => true,
        'livewire'            => false,
        'sql_queries'         => true,
        'sql_bindings'        => false,
        'queue_info'          => true,
        'command_info'        => true,
        'http_client_requests'=> true,
        'notifications'       => false,
    ],

    'tracing' => [
        'queue_job_transactions'       => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs'                   => true,
        'sql_queries'                  => true,
        'sql_origin'                   => true,
        'views'                        => false,
        'livewire'                     => false,
        'http_client_requests'         => true,
        'redis_commands'               => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'redis_origin'                 => true,
        'console_commands'             => false,
    ],

    'send_default_pii' => false,

    'environment' => env('APP_ENV', 'production'),

    'release' => env('APP_VERSION', null),

];
