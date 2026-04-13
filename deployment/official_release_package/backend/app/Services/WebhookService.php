<?php

namespace App\Services;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Fire an event to all active webhook endpoints subscribed to it.
     * Each endpoint gets its own queued delivery job.
     */
    public function dispatch(int $companyId, string $event, array $payload, string $traceId): void
    {
        $endpoints = WebhookEndpoint::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereJsonContains('events', $event)
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'company_id'          => $companyId,
                'webhook_endpoint_id' => $endpoint->id,
                'event'               => $event,
                'payload'             => $payload,
                'status'              => 'pending',
                'attempt'             => 0,
                'trace_id'            => $traceId,
                'next_attempt_at'     => now(),
            ]);

            DispatchWebhookJob::dispatch($delivery->id, $traceId)
                ->onQueue('default');

            Log::info('webhook.queued', [
                'delivery_id' => $delivery->id,
                'event'       => $event,
                'endpoint'    => $endpoint->url,
                'trace_id'    => $traceId,
            ]);
        }
    }

    /**
     * Generate HMAC-SHA256 signature for a payload.
     * Header format: t={timestamp},v1={signature}
     */
    public static function sign(string $secret, array $payload, int $timestamp): string
    {
        $body     = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signed   = "{$timestamp}.{$body}";
        $hmac     = hash_hmac('sha256', $signed, $secret);

        return "t={$timestamp},v1={$hmac}";
    }

    /**
     * Verify an inbound webhook signature.
     */
    public static function verify(string $secret, array $payload, string $signatureHeader, int $toleranceSeconds = 300): bool
    {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = explode('=', $part, 2);
            $parts[$k] = $v;
        }

        if (empty($parts['t']) || empty($parts['v1'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];
        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $body    = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signed  = "{$timestamp}.{$body}";
        $expected = hash_hmac('sha256', $signed, $secret);

        return hash_equals($expected, $parts['v1']);
    }
}
