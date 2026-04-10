<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
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

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public int $timeout = 30;

    public bool $failOnTimeout = true;

    public function __construct(
        private readonly int $deliveryId,
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
                    'Content-Type'        => 'application/json',
                    'X-Webhook-Event'     => $delivery->event,
                    'X-Webhook-Delivery'  => (string) $delivery->id,
                    'X-Webhook-Signature' => $signature,
                    'X-Trace-Id'          => $this->traceId,
                ])
                ->send('POST', $endpoint->url, ['body' => $body]);

            $httpStatus = $response->status();
            $success    = $response->successful();

            if ($success) {
                $delivery->update([
                    'status'          => 'delivered',
                    'attempt'         => $attempt,
                    'http_status'     => $httpStatus,
                    'response_body'   => substr($response->body(), 0, 2000),
                    'next_attempt_at' => null,
                ]);

                Log::info('webhook.delivery.success', [
                    'delivery_id' => $delivery->id,
                    'event'       => $delivery->event,
                    'attempt'     => $attempt,
                    'http_status' => $httpStatus,
                    'trace_id'    => $this->traceId,
                ]);

                return;
            }

            $delivery->update([
                'status'          => 'failed',
                'attempt'         => $attempt,
                'http_status'     => $httpStatus,
                'response_body'   => substr($response->body(), 0, 2000),
                'next_attempt_at' => null,
            ]);

            Log::warning('webhook.delivery.failed_http', [
                'delivery_id' => $delivery->id,
                'attempt'     => $attempt,
                'http_status' => $httpStatus,
                'trace_id'    => $this->traceId,
            ]);

            $this->failOrThrow($delivery, $attempt, 'HTTP '.$httpStatus);
        } catch (\Throwable $e) {
            $delivery->update([
                'status'          => 'failed',
                'attempt'         => $attempt,
                'response_body'   => $e->getMessage(),
                'next_attempt_at' => null,
            ]);

            Log::error('webhook.delivery.error', [
                'delivery_id' => $delivery->id,
                'error'       => $e->getMessage(),
                'attempt'     => $attempt,
                'trace_id'    => $this->traceId,
            ]);

            $this->failOrThrow($delivery, $attempt, $e->getMessage());
        }
    }

    public function failed(?\Throwable $e): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);
        if ($delivery && $delivery->status !== 'delivered') {
            $delivery->update(['status' => 'failed', 'next_attempt_at' => null]);
        }

        Log::error('webhook.delivery.exhausted', [
            'delivery_id' => $this->deliveryId,
            'error'       => $e?->getMessage(),
            'trace_id'    => $this->traceId,
        ]);
    }

    private function failOrThrow(WebhookDelivery $delivery, int $attempt, string $detail): void
    {
        if ($this->attempts() >= $this->tries) {
            $delivery->update(['status' => 'failed', 'next_attempt_at' => null]);

            return;
        }

        throw new \RuntimeException('Webhook delivery will retry: '.$detail);
    }
}
