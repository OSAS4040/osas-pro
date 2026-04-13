<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Platform\PlatformAuditLogger;
use App\Support\SaasPlatformAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SaaS platform shell — identity (whitelist / provisioned platform user) + optional kill-switch.
 */
final class EnsurePlatformAdmin
{
    public function __construct(
        private readonly PlatformAuditLogger $platformAuditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('platform.admin_enabled', true)) {
            abort(404);
        }

        if (! SaasPlatformAccess::isPlatformOperator($request->user())) {
            return response()->json([
                'message'  => 'هذه الواجهة لمشغّلي المنصة المستقلين فقط (حساب بلا شركة + بريد أو جوال مضبوط في إعدادات المنصة).',
                'code'     => 'PLATFORM_ACCESS_ONLY',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $user = $request->user();
        if ($user !== null && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $this->platformAuditLogger->record(
                $user,
                'platform.mutation',
                $request,
                ['path' => $request->path(), 'method' => $request->method()],
            );
        }

        return $next($request);
    }
}
