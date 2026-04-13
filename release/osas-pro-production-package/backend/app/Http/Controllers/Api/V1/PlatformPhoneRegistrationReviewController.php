<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RegistrationProfile;
use App\Services\PhoneRegistration\ApprovePhoneCompanyRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformPhoneRegistrationReviewController extends Controller
{
    public function __construct(
        private readonly ApprovePhoneCompanyRegistrationService $approvePhoneCompanyRegistrationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = RegistrationProfile::query()
            ->with(['user'])
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => $q,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $profile = RegistrationProfile::query()->findOrFail($id);
        $this->approvePhoneCompanyRegistrationService->approve($profile, $request->user());

        return response()->json([
            'message'  => 'تم اعتماد الطلب وإنشاء المنشأة.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = RegistrationProfile::query()->findOrFail($id);
        $this->approvePhoneCompanyRegistrationService->reject($profile, $request->user(), $data['notes'] ?? null);

        return response()->json([
            'message'  => 'تم رفض الطلب.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function requestMoreInfo(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $profile = RegistrationProfile::query()->findOrFail($id);
        $this->approvePhoneCompanyRegistrationService->requestMoreInfo($profile, $request->user(), $data['notes']);

        return response()->json([
            'message'  => 'تم طلب معلومات إضافية.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $profile = RegistrationProfile::query()->findOrFail($id);
        $this->approvePhoneCompanyRegistrationService->suspend($profile, $request->user(), $data['notes'] ?? null);

        return response()->json([
            'message'  => 'تم تعليق الطلب.',
            'trace_id' => app('trace_id'),
        ]);
    }
}
