<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TraceRequestMiddleware;
use App\Http\Middleware\TenantScopeMiddleware;
use App\Http\Middleware\GlobalTenantGuardMiddleware;
use App\Http\Middleware\FinancialOperationProtectionMiddleware;
use App\Http\Middleware\SubscriptionMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\ApiKeyAuthMiddleware;
use App\Http\Middleware\ApiUsageLoggingMiddleware;
use App\Http\Middleware\RequirePermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withProviders([
        \App\Providers\AuthServiceProvider::class,
        \App\Providers\IntelligentServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(TraceRequestMiddleware::class);

        $middleware->alias([
            'tenant'               => TenantScopeMiddleware::class,
            'global.tenant'        => GlobalTenantGuardMiddleware::class,
            'financial.protection' => FinancialOperationProtectionMiddleware::class,
            'branch.scope'         => \App\Http\Middleware\BranchScopeMiddleware::class,
            'subscription'         => SubscriptionMiddleware::class,
            'idempotent'           => IdempotencyMiddleware::class,
            'auth.apikey'          => ApiKeyAuthMiddleware::class,
            'api.log'              => ApiUsageLoggingMiddleware::class,
            'permission'           => RequirePermissionMiddleware::class,
            'intelligent.internal' => \App\Http\Middleware\EnsureIntelligentInternalAccess::class,
            'intelligent.phase2'   => \App\Http\Middleware\EnsurePhase2ReadonlyEnabled::class,
        ]);

        // Bearer token auth only — no stateful SPA session needed
        // $middleware->api(append: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (\Throwable $e) {
            if (app()->bound('sentry')) {
                \Sentry\Laravel\Integration::captureUnhandledException($e);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'  => 'Unauthenticated.',
                    'trace_id' => app('trace_id'),
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'  => 'This action is unauthorized.',
                    'trace_id' => app('trace_id'),
                ], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'  => 'Validation failed.',
                    'errors'   => $e->errors(),
                    'trace_id' => app('trace_id'),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'message'  => "{$model} not found.",
                    'trace_id' => app('trace_id'),
                ], 404);
            }
        });

        $exceptions->render(function (\DomainException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message'  => $e->getMessage(),
                    'trace_id' => app('trace_id'),
                ], 422);
            }
        });
    })->create();
