<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Services\Platform\PlatformPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
/**
 * فحص تشغيلي مؤقت — قراءة فقط، لا تعديلات مالية.
 * يُفعَّل عبر config/platform_subscriptions.php (`PLATFORM_SUBSCRIPTIONS_DEBUG_HEALTH`).
 */
final class PlatformSubscriptionsDebugHealthController extends Controller
{
    public function __invoke(Request $request, PlatformPermissionService $permissions): JsonResponse
    {
        if (! (bool) config('platform_subscriptions.debug_health_enabled')) {
            abort(404);
        }

        $user = $request->user();
        abort_unless(
            $user !== null && $permissions->hasPermission($user, 'platform.subscription.manage'),
            403,
            'PLATFORM_PERMISSION_REQUIRED',
        );

        $publicRoot = config('filesystems.disks.public.root');
        $diskOk = is_string($publicRoot) && $publicRoot !== '' && is_dir($publicRoot);

        return response()->json([
            'routes' => true,
            'auth' => $user !== null,
            'disk_public' => $diskOk,
            'sample_data' => [
                'subscriptions' => Subscription::withoutGlobalScopes()->count(),
                'invoices' => Invoice::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('type', 'subscription')
                    ->count(),
                'payment_orders' => PaymentOrder::query()->count(),
                'bank_transactions' => BankTransaction::query()->count(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
