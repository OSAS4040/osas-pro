<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Work order WhatsApp notifications (customer operational messages)
    |--------------------------------------------------------------------------
    |
    | When false, WorkOrderService will not dispatch NotifyCustomerWorkOrderWhatsAppJob,
    | and any queued job that still runs will exit quietly without retries/failures.
    | Independent of ledger, subscriptions, and payment queues.
    |
    */
    'enabled' => filter_var(
        (string) env('WHATSAPP_WORK_ORDER_NOTIFICATIONS_ENABLED', 'true'),
        FILTER_VALIDATE_BOOL
    ),
];
