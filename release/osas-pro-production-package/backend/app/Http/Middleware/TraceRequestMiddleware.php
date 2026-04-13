<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TraceRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $request->header('X-Trace-Id') ?? (string) Str::uuid();
        $correlationId = $request->header('X-Correlation-Id') ?? (string) Str::uuid();

        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('correlation_id', $correlationId);
        app()->instance('trace_id', $traceId);
        app()->instance('correlation_id', $correlationId);

        $response = $next($request);

        $response->headers->set('X-Trace-Id', $traceId);
        $response->headers->set('X-Correlation-Id', $correlationId);

        $this->setSentryContext($request, $traceId);

        return $response;
    }

    private function setSentryContext(Request $request, string $traceId): void
    {
        if (! app()->bound('sentry')) {
            return;
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($request, $traceId): void {
            $scope->setTag('trace_id', $traceId);

            $user = $request->user();
            if ($user) {
                $scope->setUser([
                    'id'         => $user->id,
                    'email'      => $user->email,
                    'company_id' => $user->company_id,
                    'branch_id'  => $user->branch_id,
                ]);
                $scope->setTag('company_id', (string) $user->company_id);
                $scope->setTag('branch_id',  (string) ($user->branch_id ?? ''));
            }

            $apiKey = $request->attributes->get('api_key');
            if ($apiKey) {
                $scope->setTag('api_key_id',  $apiKey->key_id);
                $scope->setTag('company_id',  (string) $apiKey->company_id);
            }

            $scope->setContext('request', [
                'method'   => $request->method(),
                'path'     => $request->path(),
                'trace_id' => $traceId,
            ]);
        });
    }
}
