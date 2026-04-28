<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StorePlatformTenantNavHideRequest;
use App\Models\PlatformTenantNavHide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformTenantNavHideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PlatformTenantNavHide::query()->orderByDesc('id');

        if ($request->filled('scope')) {
            $q->where('scope', (string) $request->input('scope'));
        }
        if ($request->filled('nav_key')) {
            $q->where('nav_key', (string) $request->input('nav_key'));
        }
        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->input('company_id'));
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('customer_id')) {
            $q->where('customer_id', (int) $request->input('customer_id'));
        }

        $rows = $q->limit(500)->get();

        return response()->json([
            'data'     => $rows,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(StorePlatformTenantNavHideRequest $request): JsonResponse
    {
        $scope = (string) $request->input('scope');
        $navKey = (string) $request->input('nav_key');

        $attrs = [
            'nav_key' => $navKey,
            'scope'   => $scope,
            'company_id'  => null,
            'user_id'     => null,
            'customer_id' => null,
        ];
        if ($scope === 'company') {
            $attrs['company_id'] = (int) $request->input('company_id');
        } elseif ($scope === 'user') {
            $attrs['user_id'] = (int) $request->input('user_id');
        } else {
            $attrs['customer_id'] = (int) $request->input('customer_id');
        }

        $q = PlatformTenantNavHide::query()
            ->where('nav_key', $navKey)
            ->where('scope', $scope);
        if ($scope === 'company') {
            $q->where('company_id', $attrs['company_id']);
        } elseif ($scope === 'user') {
            $q->where('user_id', $attrs['user_id']);
        } else {
            $q->where('customer_id', $attrs['customer_id']);
        }
        $existing = $q->first();
        if ($existing !== null) {
            return response()->json([
                'data'     => $existing,
                'message'  => 'القاعدة موجودة مسبقاً',
                'trace_id' => app('trace_id'),
            ]);
        }

        $row = PlatformTenantNavHide::query()->create($attrs);

        return response()->json([
            'data'     => $row,
            'message'  => 'تم إنشاء قاعدة الإخفاء',
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $deleted = PlatformTenantNavHide::query()->whereKey($id)->delete();

        return response()->json([
            'deleted'  => (bool) $deleted,
            'trace_id' => app('trace_id'),
        ]);
    }
}
