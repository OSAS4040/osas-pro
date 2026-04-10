<?php

namespace App\Support\Observability;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Forwards structured ledger-critical log lines to an optional HTTPS webhook (Slack/Teams/custom).
 * Must never throw — failures stay in HTTP client logs only.
 */
final class LedgerAlertWebhookNotifier
{
    public const LOG_MESSAGE = 'ledger.alert.ledger_posting_failed';

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyFromLogContext(array $context): void
    {
        $url = config('ledger_alerts.webhook_url');
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        $payload = [
            'event'     => self::LOG_MESSAGE,
            'timestamp' => now()->toIso8601String(),
            'context'   => Arr::only($context, [
                'code',
                'source',
                'company_id',
                'invoice_id',
                'trace_id',
                'previous_class',
            ]),
        ];

        try {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $headers = ['Content-Type' => 'application/json'];
        $secret  = config('ledger_alerts.webhook_secret');
        if (is_string($secret) && $secret !== '') {
            $headers['X-Ledger-Alert-Signature'] = hash_hmac('sha256', $body, $secret);
        }

        $timeout = (int) config('ledger_alerts.timeout_seconds', 5);

        try {
            Http::withHeaders($headers)
                ->withBody($body, 'application/json')
                ->timeout($timeout)
                ->connectTimeout(2)
                ->post($url);
        } catch (\Throwable) {
            // Intentionally swallow — do not affect user-facing requests.
        }
    }
}
