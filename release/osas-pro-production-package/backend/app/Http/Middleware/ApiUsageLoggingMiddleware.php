<?php

namespace App\Http\Middleware;

use App\Models\ApiUsageLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs API key usage after each request.
 * Only activates when an API key is present on the request (external routes).
 * Runs after response is built to capture http_status and response_time_ms.
 */
class ApiUsageLoggingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $apiKey = $request->attributes->get('api_key')
            ?? app()->bound('api_key') ? app('api_key') : null;

        if ($apiKey) {
            $elapsed = (int) round((microtime(true) - $startTime) * 1000);

            try {
                ApiUsageLog::create([
                    'company_id'       => $apiKey->company_id,
                    'api_key_id'       => $apiKey->id,
                    'method'           => $request->method(),
                    'endpoint'         => $request->path(),
                    'http_status'      => $response->getStatusCode(),
                    'response_time_ms' => $elapsed,
                    'ip_address'       => $request->ip(),
                    'trace_id'         => app('trace_id'),
                    'created_at'       => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('api_usage_log.failed', [
                    'error'    => $e->getMessage(),
                    'trace_id' => app('trace_id'),
                ]);
            }
        }

        return $response;
    }
}
