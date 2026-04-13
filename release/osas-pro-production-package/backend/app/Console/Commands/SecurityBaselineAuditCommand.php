<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionMethod;

class SecurityBaselineAuditCommand extends Command
{
    protected $signature = 'security:baseline-audit
        {--out-dir= : Output directory (default: reports/security-baseline-<timestamp>)}';

    protected $description = 'Generate wave-1 security baseline: sensitive endpoints, route-permission matrix, and policy coverage report.';

    /** @var string[] */
    private array $sensitiveKeywords = [
        'wallet', 'payment', 'invoice', 'pos', 'subscription', 'plan',
        'role', 'permission', 'company', 'branch', 'user', 'governance',
        'api-keys', 'webhooks', 'support', 'internal', 'plugins',
    ];

    /** @var string[] */
    private array $nonSensitiveExactUris = [
        '/api/v1/health',
        '/api/v1/auth/login',
        '/api/v1/auth/register',
        '/api/v1/auth/logout',
        '/api/v1/auth/me',
        '/api/v1/plans',
    ];

    public function handle(): int
    {
        $ts = now()->format('Ymd-His');
        $outDir = (string) ($this->option('out-dir') ?: base_path("reports/security-baseline-{$ts}"));
        if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
            $this->error("Unable to create output directory: {$outDir}");

            return self::FAILURE;
        }

        $rows = [];
        $sensitiveRows = [];
        $total = 0;

        foreach (Route::getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $method = $methods[0] ?? 'GET';
            $uri = '/'.ltrim($route->uri(), '/');
            $actionName = ltrim((string) ($route->getActionName() ?? ''), '\\');
            $middleware = $route->gatherMiddleware();

            $requiredPermissions = $this->extractPermissionMiddleware($middleware);
            $hasPermissionMiddleware = $requiredPermissions !== [];

            $authorizeDetected = false;
            $formRequestAuthorizeDetected = false;
            if ($actionName !== '' && $actionName !== 'Closure' && str_contains($actionName, '@')) {
                [$class, $fn] = explode('@', $actionName, 2);
                $authorizeDetected = $this->controllerMethodHasAuthorizeCall($class, $fn);
                $formRequestAuthorizeDetected = $this->controllerMethodHasFormRequestAuthorize($class, $fn);
            }

            $isSensitive = $this->isSensitiveEndpoint($method, $uri, $requiredPermissions);
            $transitionGuard = $this->detectTransitionGuardSignal($actionName, $uri, $method);

            $risk = 'low';
            $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            $hasSpecialGuard = $this->hasSpecialGuardMiddleware($middleware);
            if ($isSensitive && $isMutating && ! $hasPermissionMiddleware && ! $authorizeDetected && ! $formRequestAuthorizeDetected) {
                $risk = $hasSpecialGuard ? 'medium' : 'high';
            } elseif ($isSensitive && ! $hasPermissionMiddleware) {
                $risk = $hasSpecialGuard ? 'low-medium' : 'medium';
            } elseif ($isSensitive) {
                $risk = 'low-medium';
            }

            $row = [
                'method' => $method,
                'uri' => $uri,
                'action' => $actionName,
                'middleware' => implode('|', $middleware),
                'permissions' => implode('|', $requiredPermissions),
                'sensitive' => $isSensitive ? 'yes' : 'no',
                'controller_authorize_call' => $authorizeDetected ? 'yes' : 'no',
                'form_request_authorize' => $formRequestAuthorizeDetected ? 'yes' : 'no',
                'transition_guard_hint' => $transitionGuard['hint'],
                'transition_guard_signal' => $transitionGuard['signal'],
                'risk' => $risk,
            ];
            $rows[] = $row;
            $total++;

            if ($isSensitive) {
                $sensitiveRows[] = $row;
            }
        }

        $highRisk = array_values(array_filter($sensitiveRows, static fn (array $r): bool => $r['risk'] === 'high'));
        $mediumRisk = array_values(array_filter($sensitiveRows, static fn (array $r): bool => $r['risk'] === 'medium'));

        $this->writeCsv("{$outDir}/route-permission-matrix.csv", $rows);
        file_put_contents(
            "{$outDir}/sensitive-endpoints.json",
            json_encode(
                [
                    'generated_at' => now()->toIso8601String(),
                    'total_routes' => $total,
                    'sensitive_routes' => count($sensitiveRows),
                    'high_risk_sensitive_routes' => count($highRisk),
                    'medium_risk_sensitive_routes' => count($mediumRisk),
                    'rows' => $sensitiveRows,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );

        file_put_contents(
            "{$outDir}/policy-coverage-report.json",
            json_encode(
                [
                    'generated_at' => now()->toIso8601String(),
                    'summary' => [
                        'total_routes' => $total,
                        'sensitive_routes' => count($sensitiveRows),
                        'high_risk_sensitive_routes' => count($highRisk),
                        'medium_risk_sensitive_routes' => count($mediumRisk),
                    ],
                    'transition_guard_summary' => $this->buildTransitionGuardSummary($sensitiveRows),
                    'high_risk_sensitive_routes' => $highRisk,
                    'medium_risk_sensitive_routes' => $mediumRisk,
                    'transition_guard_hints' => array_values(array_filter(
                        $sensitiveRows,
                        static fn (array $row): bool => $row['transition_guard_hint'] !== 'not_applicable'
                    )),
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            )
        );

        $this->info("SECURITY_BASELINE_DIR={$outDir}");
        $this->info("TOTAL_ROUTES={$total}");
        $this->info('SENSITIVE_ROUTES='.count($sensitiveRows));
        $this->info('HIGH_RISK_SENSITIVE_ROUTES='.count($highRisk));
        $this->info('MEDIUM_RISK_SENSITIVE_ROUTES='.count($mediumRisk));

        return self::SUCCESS;
    }

    /**
     * @param  string[]  $middleware
     * @return string[]
     */
    private function extractPermissionMiddleware(array $middleware): array
    {
        $out = [];
        foreach ($middleware as $mw) {
            if (str_starts_with($mw, 'permission:')) {
                $out[] = Str::after($mw, 'permission:');
            }
        }

        return $out;
    }

    /**
     * @param  string[]  $requiredPermissions
     */
    private function isSensitiveEndpoint(string $method, string $uri, array $requiredPermissions): bool
    {
        if (in_array($uri, $this->nonSensitiveExactUris, true)) {
            return false;
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }
        if ($requiredPermissions !== []) {
            return true;
        }

        $lowerUri = Str::lower($uri);
        foreach ($this->sensitiveKeywords as $keyword) {
            if (str_contains($lowerUri, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $middleware
     */
    private function hasSpecialGuardMiddleware(array $middleware): bool
    {
        $guards = [
            'auth:sanctum',
            'intelligent.internal',
            'intelligent.phase2',
            'subscription',
            'financial.protection',
            'branch.scope',
            'api.log',
            'auth.apikey',
        ];
        foreach ($middleware as $mw) {
            foreach ($guards as $guard) {
                if ($mw === $guard) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{hint:string, signal:string}
     */
    private function detectTransitionGuardSignal(string $actionName, string $uri, string $method): array
    {
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return ['hint' => 'not_applicable', 'signal' => 'read_only_route'];
        }

        $uriLower = Str::lower($uri);
        $actionLower = Str::lower($actionName);
        $transitionKeywords = [
            'status', 'receive', 'approve', 'reject',
            'cancel', 'close', 'reopen', 'renew',
        ];

        $looksLikeTransitionRoute = $this->containsAnyKeyword($uriLower, $transitionKeywords)
            || $this->containsAnyKeyword($actionLower, $transitionKeywords);

        if (! $looksLikeTransitionRoute) {
            return ['hint' => 'not_applicable', 'signal' => 'non_transition_mutation'];
        }

        if ($actionName === '' || $actionName === 'Closure' || ! str_contains($actionName, '@')) {
            return ['hint' => 'transition_guard_unknown', 'signal' => 'closure_or_unresolved_action'];
        }

        [$class, $fn] = explode('@', $actionName, 2);

        if (! class_exists($class) || ! method_exists($class, $fn)) {
            return ['hint' => 'transition_guard_unknown', 'signal' => 'action_not_reflectable'];
        }

        try {
            $ref = new ReflectionMethod($class, $fn);
        } catch (ReflectionException) {
            return ['hint' => 'transition_guard_unknown', 'signal' => 'reflection_failed'];
        }

        $file = $ref->getFileName();
        if (! is_string($file) || ! is_file($file)) {
            return ['hint' => 'transition_guard_unknown', 'signal' => 'source_unavailable'];
        }

        $source = file_get_contents($file);
        if (! is_string($source)) {
            return ['hint' => 'transition_guard_unknown', 'signal' => 'source_unreadable'];
        }

        $pattern = '/function\s+'.preg_quote($fn, '/').'\s*\([^)]*\)\s*:[^{]*\{(.*?)\n\}/s';
        $body = $source;
        if (preg_match($pattern, $source, $m)) {
            $body = (string) ($m[1] ?? $source);
        }

        $hasTransitionSignal = str_contains($body, 'allowedTransitions')
            || str_contains($body, 'is not allowed')
            || str_contains($body, 'cannot be received')
            || str_contains($body, 'cannot accept a payment in its current status')
            || str_contains($body, 'in_array(');

        if ($hasTransitionSignal) {
            return ['hint' => 'transition_guard_present', 'signal' => 'controller_transition_check_detected'];
        }

        return ['hint' => 'transition_guard_missing_or_unknown', 'signal' => 'no_transition_signal_detected'];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<string, int>
     */
    private function buildTransitionGuardSummary(array $rows): array
    {
        $summary = [
            'present' => 0,
            'missing_or_unknown' => 0,
            'unknown' => 0,
            'not_applicable' => 0,
        ];

        foreach ($rows as $row) {
            $hint = $row['transition_guard_hint'] ?? 'not_applicable';
            if ($hint === 'transition_guard_present') {
                $summary['present']++;
            } elseif ($hint === 'transition_guard_missing_or_unknown') {
                $summary['missing_or_unknown']++;
            } elseif ($hint === 'transition_guard_unknown') {
                $summary['unknown']++;
            } else {
                $summary['not_applicable']++;
            }
        }

        return $summary;
    }

    /**
     * @param  string[]  $keywords
     */
    private function containsAnyKeyword(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function controllerMethodHasAuthorizeCall(string $class, string $method): bool
    {
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }
        try {
            $ref = new ReflectionMethod($class, $method);
        } catch (ReflectionException) {
            return false;
        }

        $file = $ref->getFileName();
        if (! is_string($file) || ! is_file($file)) {
            return false;
        }
        $source = file_get_contents($file);
        if (! is_string($source)) {
            return false;
        }

        $pattern = '/function\s+'.preg_quote($method, '/').'\s*\([^)]*\)\s*:[^{]*\{(.*?)\n\}/s';
        if (! preg_match($pattern, $source, $m)) {
            return str_contains($source, '->authorize(') || str_contains($source, 'Gate::authorize(');
        }
        $body = (string) ($m[1] ?? '');

        return str_contains($body, '->authorize(') || str_contains($body, 'Gate::authorize(');
    }

    private function controllerMethodHasFormRequestAuthorize(string $class, string $method): bool
    {
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return false;
        }
        try {
            $ref = new ReflectionMethod($class, $method);
        } catch (ReflectionException) {
            return false;
        }

        foreach ($ref->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type || $type->isBuiltin()) {
                continue;
            }
            $name = $type->getName();
            if (! class_exists($name) || ! is_subclass_of($name, FormRequest::class)) {
                continue;
            }

            if (method_exists($name, 'authorize')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $fh = fopen($path, 'wb');
        if ($fh === false) {
            return;
        }
        if ($rows === []) {
            fclose($fh);

            return;
        }
        fputcsv($fh, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($fh, $row);
        }
        fclose($fh);
    }
}

