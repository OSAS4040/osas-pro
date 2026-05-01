<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OSAS bulk work orders (single-tenant burst)
    |--------------------------------------------------------------------------
    |
    | POST /api/v1/work-orders/bulk accepts many vehicle_ids, persists a batch row,
    | and processes creation in queued chunks (never 200 synchronous inserts per request).
    |
    */
    'bulk_chunk_size' => max(10, min(200, (int) env('WORK_ORDER_BULK_CHUNK_SIZE', 50))),

    'bulk_max_vehicles' => max(1, min(5000, (int) env('WORK_ORDER_BULK_MAX_VEHICLES', 500))),

    'bulk_queue' => env('WORK_ORDER_BULK_QUEUE', 'default'),

    /**
     * When true (e.g. PHPUnit), the orchestrator runs synchronously after the HTTP handler
     * so assertions see completed items without relying on terminate callbacks.
     */
    'bulk_inline_in_tests' => filter_var(env('WORK_ORDER_BULK_INLINE_IN_TESTS', true), FILTER_VALIDATE_BOOL),

];
