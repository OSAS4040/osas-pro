<?php

declare(strict_types=1);

return [
    /**
     * If > 0, log a warning when WorkOrderService::create exceeds this many milliseconds.
     * Off by default; set OBS_WORK_ORDER_CREATE_WARN_MS in staging/load analysis.
     */
    'work_order_create_warn_ms' => max(0, (int) env('OBS_WORK_ORDER_CREATE_WARN_MS', 0)),
];
