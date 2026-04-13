<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Platform\PlatformPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained platform IAM — must run after {@see EnsurePlatformAdmin}.
 */
final class EnsurePlatformPermission
{
    public function __construct(
        private readonly PlatformPermissionService $platformPermissionService,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $this->platformPermissionService->hasPermission($user, $permission)) {
            return response()->json([
                'message'  => 'لا تملك صلاحية المنصة المطلوبة لهذا الإجراء.',
                'code'     => 'PLATFORM_PERMISSION_DENIED',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        return $next($request);
    }
}
