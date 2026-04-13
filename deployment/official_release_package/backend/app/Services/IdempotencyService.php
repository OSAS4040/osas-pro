<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Log;

class IdempotencyService
{
    private const DEFAULT_TTL_HOURS = 24;

    /**
     * Check if a key already exists.
     * Returns the stored response snapshot if duplicate, or null if new.
     *
     * @throws \DomainException when same key is reused with a different payload hash
     */
    public function check(int $companyId, string $key, string $endpoint, string $requestHash): ?array
    {
        $record = IdempotencyKey::where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if (! $record) {
            return null;
        }

        if ($record->expires_at->isPast()) {
            $record->delete();
            return null;
        }

        if ($record->request_hash !== $requestHash) {
            Log::warning('idempotency.payload_mismatch', [
                'company_id' => $companyId,
                'key'        => $key,
                'endpoint'   => $endpoint,
                'trace_id'   => app()->bound('trace_id') ? app('trace_id') : null,
            ]);

            throw new \DomainException(
                'Idempotency key already used with a different request payload. ' .
                'Use a new key or resend the original payload.'
            );
        }

        return $record->response_snapshot
            ? json_decode($record->response_snapshot, true)
            : null;
    }

    /**
     * Store a key and its associated response.
     */
    public function store(
        int    $companyId,
        string $key,
        string $endpoint,
        string $requestHash,
        array  $response,
        int    $ttlHours = self::DEFAULT_TTL_HOURS,
    ): void {
        IdempotencyKey::updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            [
                'endpoint'          => $endpoint,
                'trace_id'          => app()->bound('trace_id') ? app('trace_id') : null,
                'request_hash'      => $requestHash,
                'response_snapshot' => json_encode($response),
                'expires_at'        => now()->addHours($ttlHours),
            ]
        );
    }

    /**
     * Build a deterministic hash of the request payload.
     */
    public function hashPayload(array $payload): string
    {
        unset($payload['idempotency_key']);
        ksort($payload);
        return hash('sha256', json_encode($payload));
    }
}
