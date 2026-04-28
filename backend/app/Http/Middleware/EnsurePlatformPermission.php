<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Platform\PlatformPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained platform IAM — must run after {@see EnsurePlatformAdmin}.
 *
 * المعامل `$permission`: مفتاح واحد، أو عدة مفاتيح مفصولة بـ {@see '|'} (يكفي تحقيق أحدهم).
 */
final class EnsurePlatformPermission
{
    public function __construct(
        private readonly PlatformPermissionService $platformPermissionService,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message'  => 'Unauthenticated.',
                'code'     => 'UNAUTHENTICATED',
                'trace_id' => app('trace_id'),
            ], 401);
        }

        $candidates = str_contains($permission, '|')
            ? array_values(array_filter(array_map('trim', explode('|', $permission))))
            : [$permission];

        $allowed = false;
        foreach ($candidates as $perm) {
            if ($perm === '') {
                continue;
            }
            if ($this->platformPermissionService->hasPermission($user, $perm)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            return response()->json([
                'message'  => 'لا تملك صلاحية المنصة المطلوبة لهذا الإجراء.',
                'code'     => 'PLATFORM_PERMISSION_DENIED',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        return $next($request);
    }
}
