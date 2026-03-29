<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $companyId   = app('tenant_company_id');
        $endpoint    = $request->path();
        $requestHash = hash('sha256', json_encode($request->all()));

        $existing = IdempotencyKey::where('company_id', $companyId)
            ->where('key', $key)
            ->first();

        if ($existing) {
            if ($existing->request_hash !== $requestHash) {
                return response()->json([
                    'message'  => 'Idempotency key already used with different payload.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }

            if ($existing->response_snapshot) {
                return response()->json(
                    json_decode($existing->response_snapshot, true),
                    200,
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
            IdempotencyKey::where('company_id', $companyId)
                ->where('key', $key)
                ->update(['response_snapshot' => $response->getContent()]);
        }

        return $response;
    }
}
