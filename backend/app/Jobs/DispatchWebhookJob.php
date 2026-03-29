<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 1;
    public int    $timeout = 30;
    public bool   $failOnTimeout = true;

    private const MAX_ATTEMPTS    = 3;
    private const BACKOFF_SECONDS = [60, 300, 900];

    public function __construct(
        private readonly int    $deliveryId,
        private readonly string $traceId,
    ) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'skipped']);
            return;
        }

        $attempt   = $delivery->attempt + 1;
        $timestamp = time();

        $signature = WebhookService::sign(
            secret:    $endpoint->secret_hash,
            payload:   $delivery->payload,
            timestamp: $timestamp,
        );

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'       => 'application/json',
                    'X-Webhook-Event'    => $delivery->event,
                    'X-Webhook-Delivery' => (string) $delivery->id,
                    'X-Webhook-Signature' => $signature,
                    'X-Trace-Id'         => $this->traceId,
                ])
                ->send('POST', $endpoint->url, ['body' => $body]);

            $httpStatus = $response->status();
            $success    = $response->successful();

            $delivery->update([
                'status'      => $success ? 'delivered' : 'failed',
                'attempt'     => $attempt,
                'http_status' => $httpStatus,
                'response_body' => substr($response->body(), 0, 2000),
                'next_attempt_at' => null,
            ]);

            Log::info('webhook.delivery.' . ($success ? 'success' : 'failed'), [
                'delivery_id' => $delivery->id,
                'event'       => $delivery->event,
                'attempt'     => $attempt,
                'http_status' => $httpStatus,
                'trace_id'    => $this->traceId,
            ]);

            if (! $success && $attempt < self::MAX_ATTEMPTS) {
                $this->scheduleRetry($delivery, $attempt);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'status'        => 'failed',
                'attempt'       => $attempt,
                'response_body' => $e->getMessage(),
                'next_attempt_at' => null,
            ]);

            Log::error('webhook.delivery.error', [
                'delivery_id' => $delivery->id,
                'error'       => $e->getMessage(),
                'attempt'     => $attempt,
                'trace_id'    => $this->traceId,
            ]);

            if ($attempt < self::MAX_ATTEMPTS) {
                $this->scheduleRetry($delivery, $attempt);
            }
        }
    }

    private function scheduleRetry(WebhookDelivery $delivery, int $attempt): void
    {
        $delaySeconds = self::BACKOFF_SECONDS[$attempt - 1] ?? 900;
        $nextAt       = now()->addSeconds($delaySeconds);

        $delivery->update([
            'status'          => 'pending',
            'next_attempt_at' => $nextAt,
        ]);

        self::dispatch($this->deliveryId, $this->traceId)
            ->onQueue('default')
            ->delay($nextAt);

        Log::info('webhook.delivery.retry_scheduled', [
            'delivery_id' => $delivery->id,
            'next_attempt' => $attempt + 1,
            'retry_at'    => $nextAt->toIso8601String(),
            'trace_id'    => $this->traceId,
        ]);
    }
}
