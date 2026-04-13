<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests using an API key (Bearer token strategy).
 * Validates:
 *  - Key existence and validity
 *  - Not revoked
 *  - Not expired
 *  - Optional scope check (passed as middleware parameter)
 *  - Per-key rate limiting via Redis (sliding window)
 */
class ApiKeyAuthMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredScope = null): Response
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'message'  => 'API key required.',
                'trace_id' => app('trace_id'),
            ], 401);
        }

        $rawKey  = substr($authHeader, 7);
        $keyHash = hash('sha256', $rawKey);

        $apiKey = ApiKey::whereNull('revoked_at')
            ->where('secret_hash', $keyHash)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (! $apiKey) {
            return response()->json([
                'message'  => 'Invalid or expired API key.',
                'trace_id' => app('trace_id'),
            ], 401);
        }

        if ($requiredScope && ! $this->hasScope($apiKey, $requiredScope)) {
            return response()->json([
                'message'  => "API key missing required scope: {$requiredScope}.",
                'trace_id' => app('trace_id'),
            ], 403);
        }

        if (! $this->checkRateLimit($apiKey)) {
            return response()->json([
                'message'  => 'Rate limit exceeded.',
                'trace_id' => app('trace_id'),
            ], 429);
        }

        app()->instance('tenant_company_id', $apiKey->company_id);
        app()->instance('api_key', $apiKey);

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    private function hasScope(ApiKey $apiKey, string $scope): bool
    {
        $scopes = $apiKey->permissions_scope ?? [];

        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    /**
     * Sliding window rate limiter using Redis.
     * Window: 60 seconds. Limit: api_key->rate_limit requests per minute.
     */
    private function checkRateLimit(ApiKey $apiKey): bool
    {
        $limit  = (int) ($apiKey->rate_limit ?? 1000);
        $window = 60;
        $key    = "ratelimit:apikey:{$apiKey->id}";
        $now    = microtime(true);
        $from   = $now - $window;

        $redis = Redis::connection();

        $redis->zremrangebyscore($key, '-inf', $from);

        $current = (int) $redis->zcard($key);

        if ($current >= $limit) {
            return false;
        }

        $redis->zadd($key, $now, "{$now}-" . uniqid());
        $redis->expire($key, $window + 1);

        return true;
    }
}
