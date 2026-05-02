<?php

use App\Exceptions\LedgerPostingFailedException;
use App\Http\Middleware\ApiKeyAuthMiddleware;
use App\Http\Middleware\ApiUsageLoggingMiddleware;
use App\Http\Middleware\BranchScopeMiddleware;
use App\Http\Middleware\EnsureBusinessFeatureEnabled;
use App\Http\Middleware\EnsureIntelligentInternalAccess;
use App\Http\Middleware\EnsurePhase2ReadonlyEnabled;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsurePlatformPermission;
use App\Http\Middleware\FinancialOperationProtectionMiddleware;
use App\Http\Middleware\ForceJsonForApi;
use App\Http\Middleware\GlobalTenantGuardMiddleware;
use App\Http\Middleware\IdempotencyMiddleware;
use App\Http\Middleware\RequirePermissionMiddleware;
use App\Http\Middleware\SubscriptionMiddleware;
use App\Http\Middleware\TenantScopeMiddleware;
use App\Http\Middleware\TraceRequestMiddleware;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\IntelligentServiceProvider;
use App\Services\Auth\AuthSecurityTelemetryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

if (PHP_SAPI === 'cli') {
    $argv = $_SERVER['argv'] ?? [];
    $script = isset($argv[0]) ? (string) $argv[0] : '';
    $isTestCommand = in_array('test', $argv, true)
        || in_array('pest', $argv, true)
        || in_array('phpunit', $argv, true)
        || str_contains($script, 'phpunit');

    if ($isTestCommand) {
        $pairs = [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) (getenv('TEST_DB_HOST') ?: getenv('DB_HOST') ?: 'postgres'),
            'DB_PORT' => (string) (getenv('TEST_DB_PORT') ?: getenv('DB_PORT') ?: '5432'),
            'DB_DATABASE' => (string) (getenv('TEST_DB_DATABASE') ?: 'saas_test'),
            'DB_USERNAME' => (string) (getenv('TEST_DB_USERNAME') ?: getenv('DB_USERNAME') ?: 'saas_user'),
            'DB_PASSWORD' => (string) (getenv('TEST_DB_PASSWORD') ?: getenv('DB_PASSWORD') ?: 'saas_password'),
            'CACHE_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'APP_CONFIG_CACHE' => '/tmp/laravel-testing-config.php',
            'APP_EVENTS_CACHE' => '/tmp/laravel-testing-events.php',
            'APP_ROUTES_CACHE' => '/tmp/laravel-testing-routes.php',
            'APP_SERVICES_CACHE' => '/tmp/laravel-testing-services.php',
        ];

        foreach ($pairs as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withProviders([
        AuthServiceProvider::class,
        IntelligentServiceProvider::class,
        AppServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(TraceRequestMiddleware::class);
        $middleware->api(prepend: [
            ForceJsonForApi::class,
        ]);

        $middleware->alias([
            'tenant' => TenantScopeMiddleware::class,
            'global.tenant' => GlobalTenantGuardMiddleware::class,
            'financial.protection' => FinancialOperationProtectionMiddleware::class,
            'branch.scope' => BranchScopeMiddleware::class,
            'subscription' => SubscriptionMiddleware::class,
            'idempotent' => IdempotencyMiddleware::class,
            'auth.apikey' => ApiKeyAuthMiddleware::class,
            'api.log' => ApiUsageLoggingMiddleware::class,
            'permission' => RequirePermissionMiddleware::class,
            'intelligent.internal' => EnsureIntelligentInternalAccess::class,
            'intelligent.phase2' => EnsurePhase2ReadonlyEnabled::class,
            'business.feature' => EnsureBusinessFeatureEnabled::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'platform.permission' => EnsurePlatformPermission::class,
        ]);

        // Bearer token auth only — no stateful SPA session needed
        // $middleware->api(append: [
        //     \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $apiExpectsJson = static function (Request $request): bool {
            return $request->is('api') || $request->is('api/*') || $request->expectsJson();
        };

        $exceptions->report(function (Throwable $e) {
            if (app()->bound('sentry')) {
                Integration::captureUnhandledException($e);
            }
        });

        $exceptions->render(function (AuthenticationException $e, $request) use ($apiExpectsJson) {
            if ($apiExpectsJson($request)) {
                return new JsonResponse([
                    'message' => 'Unauthenticated.',
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, $request) use ($apiExpectsJson) {
            if ($apiExpectsJson($request)) {
                return new JsonResponse([
                    'message' => 'This action is unauthorized.',
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) use ($apiExpectsJson) {
            if ($apiExpectsJson($request)) {
                $first = collect($e->errors())
                    ->flatten()
                    ->filter(static fn ($m) => is_string($m) && $m !== '')
                    ->first();

                return new JsonResponse([
                    'message' => $first ?: 'Validation failed.',
                    'errors' => $e->errors(),
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ], 422);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, $request) use ($apiExpectsJson) {
            if ($apiExpectsJson($request)) {
                $model = class_basename($e->getModel());

                return new JsonResponse([
                    'message' => "{$model} not found.",
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ], 404);
            }
        });

        $exceptions->render(function (DomainException $e, $request) use ($apiExpectsJson) {
            if ($apiExpectsJson($request)) {
                return new JsonResponse([
                    'message' => $e->getMessage(),
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ], 422);
            }
        });

        $exceptions->render(function (LedgerPostingFailedException $e, $request) use ($apiExpectsJson) {
            if ($apiExpectsJson($request)) {
                return new JsonResponse([
                    'message' => $e->getMessage(),
                    'code' => LedgerPostingFailedException::ERROR_CODE,
                    'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
                ], 503, [
                    'Retry-After' => '5',
                ]);
            }
        });

        /** 404 / 403 / … from the HTTP layer — must be JSON for /api/* (NotFoundHttpException, etc.). */
        $exceptions->render(function (HttpExceptionInterface $e, $request) use ($apiExpectsJson) {
            if (! $apiExpectsJson($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $e->getMessage();
            if ($message === '' || $message === $status) {
                $message = $status === 404 ? 'Not found.' : 'HTTP error.';
            }

            $payload = [
                'message' => $message,
                'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
            ];

            $headers = [];
            if ($status === 429) {
                $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
                $messages = (array) config('auth_security.messages.'.$locale, []);
                $payload['message'] = is_string($messages['auth.security.rate_limited'] ?? null)
                    ? (string) $messages['auth.security.rate_limited']
                    : $message;
                $payload['message_key'] = 'auth.security.rate_limited';
                $payload['reason_code'] = 'RATE_LIMITED';

                if ($e instanceof ThrottleRequestsException) {
                    $headers = $e->getHeaders();
                }

                try {
                    app(AuthSecurityTelemetryService::class)->recordRateLimitedIfAuthEndpoint($request);
                } catch (Throwable $t) {
                    report($t);
                }
            }

            return new JsonResponse($payload, $status, $headers);
        });

        /** Unhandled server errors only — does not replace specialized handlers above. */
        $exceptions->render(function (Throwable $e, $request) use ($apiExpectsJson) {
            if (! $apiExpectsJson($request)) {
                return null;
            }

            if ($e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof ValidationException
                || $e instanceof ModelNotFoundException
                || $e instanceof DomainException
                || $e instanceof LedgerPostingFailedException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            report($e);

            return new JsonResponse([
                'message' => config('app.debug')
                    ? $e->getMessage().' ['.basename($e->getFile()).':'.$e->getLine().']'
                    : 'Server error.',
                'trace_id' => app()->bound('trace_id') ? app('trace_id') : null,
            ], 500);
        });
    })->create();
