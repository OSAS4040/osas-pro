<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\SystemCapabilitiesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/system/capabilities — كتالوج قدرات للموظفين داخل المستأجر فقط (قراءة).
 */
class SystemCapabilitiesController extends Controller
{
    public function index(Request $request, SystemCapabilitiesService $service): JsonResponse
    {
        $user = $request->user();
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);
        if (! $role || ! $role->isWorkshopSide()) {
            return response()->json([
                'message'  => 'Capabilities catalogue is available to workshop-side roles only.',
                'code'     => 'capabilities_staff_only',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $companyId = (int) app('tenant_company_id');
        $company   = Company::query()->findOrFail($companyId);
        $data      = $service->buildFor($user, $company);

        return response()->json([
            'data'     => $data,
            'trace_id' => app('trace_id'),
        ]);
    }
}
