<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ledger / accounting failure webhook (optional)
    |--------------------------------------------------------------------------
    |
    | When Log::critical('ledger.alert.ledger_posting_failed', [...]) runs in non-testing
    | environments, a JSON POST is sent here if the URL is set. Use Slack/Teams/Discord
    | incoming webhooks or your own receiver. Payload excludes PII beyond ids/trace_id.
    |
    */
    'webhook_url' => env('LEDGER_ALERT_WEBHOOK_URL', ''),

    /** Optional HMAC-SHA256 of the raw JSON body, sent as X-Ledger-Alert-Signature */
    'webhook_secret' => env('LEDGER_ALERT_WEBHOOK_SECRET', ''),

    'timeout_seconds' => max(1, min(30, (int) env('LEDGER_ALERT_TIMEOUT', 5))),
];
