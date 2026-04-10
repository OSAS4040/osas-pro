<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return response()->json([
                'message'  => 'Idempotency-Key header is required.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $companyId = app('tenant_company_id');
        $endpoint  = $request->path();
        $payload   = $request->except(['idempotency_key']);
        foreach ($request->route()?->parameters() ?? [] as $name => $value) {
            if ($value instanceof Model) {
                $value = $value->getKey();
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $payload[Str::snake((string) $name)] = $this->normalizeHashScalar($value);
        }
        ksort($payload);
        $requestHash = hash('sha256', json_encode($payload));

        $existing = IdempotencyKey::where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'message'  => 'Idempotency key already used with different payload.',
                    'trace_id' => app('trace_id'),
                ], 409);
            }

            if ($existing->response_snapshot) {
                $decoded = json_decode($existing->response_snapshot, true);
                $status  = 200;
                $body    = $decoded;
                if (is_array($decoded)
                    && array_key_exists('__idempotent_v2__', $decoded)
                    && array_key_exists('__payload__', $decoded)
                    && is_array($decoded['__idempotent_v2__'])) {
                    $status = (int) ($decoded['__idempotent_v2__']['status'] ?? 200);
                    $body   = $decoded['__payload__'];
                }

                return response()->json(
                    $body,
                    $status,
                    ['X-Idempotent-Replayed' => 'true', 'X-Trace-Id' => app('trace_id')]
                );
            }
        }

        IdempotencyKey::updateOrCreate(
            ['company_id' => $companyId, 'key' => $key],
            [
                'endpoint'     => $endpoint,
                'trace_id'     => app('trace_id'),
                'request_hash' => $requestHash,
                'expires_at'   => now()->addHours(24),
            ]
        );

        $request->attributes->set('idempotency_key', $key);
        $request->attributes->set('idempotency_company_id', $companyId);

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $payload = json_decode($response->getContent(), true);
            $snapshot = json_encode([
                '__idempotent_v2__' => ['status' => $response->getStatusCode()],
                '__payload__'       => $payload,
            ]);
            IdempotencyKey::where('company_id', $companyId)
                ->where('key', $key)
                ->update(['response_snapshot' => $snapshot]);
        }

        return $response;
    }

    private function normalizeHashScalar(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value) && $value !== '' && ctype_digit($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return round($value, 10);
        }

        return $value;
    }
}
