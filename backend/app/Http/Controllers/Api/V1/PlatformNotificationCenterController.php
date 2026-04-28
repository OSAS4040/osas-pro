<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformNotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformNotificationCenterController extends Controller
{
    public function __construct(
        private readonly PlatformNotificationCenterService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'trace_id' => app('trace_id'),
            ], 401);
        }

        $notifications = $this->service->buildNotificationsFor($user);

        $category = strtolower((string) $request->query('category', ''));
        if ($category !== '') {
            $notifications = array_values(array_filter(
                $notifications,
                static fn (array $n): bool => strtolower((string) ($n['notification_type'] ?? '')) === $category
            ));
        }

        $requiresAction = filter_var($request->query('requires_action'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($requiresAction === true) {
            $notifications = array_values(array_filter(
                $notifications,
                static fn (array $n): bool => (bool) ($n['requires_action'] ?? false) === true
            ));
        }

        $limit = max(1, min((int) $request->query('limit', 50), 100));
        $slice = array_slice($notifications, 0, $limit);

        return response()->json([
            'data' => $slice,
            'meta' => [
                'total' => count($notifications),
                'unread_count' => count(array_filter($notifications, static fn (array $n): bool => ! ((bool) ($n['is_read'] ?? false)))),
                'requires_action_count' => count(array_filter($notifications, static fn (array $n): bool => (bool) ($n['requires_action'] ?? false))),
                'attention_now' => array_slice($notifications, 0, 8),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}

