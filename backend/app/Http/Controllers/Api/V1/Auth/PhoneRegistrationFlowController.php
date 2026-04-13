<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteAccountTypeRequest;
use App\Http\Requests\Auth\CompleteCompanyProfileRequest;
use App\Http\Requests\Auth\CompleteIndividualProfileRequest;
use App\Services\PhoneRegistration\CompleteRegistrationProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneRegistrationFlowController extends Controller
{
    public function __construct(
        private readonly CompleteRegistrationProfileService $completeRegistrationProfileService,
    ) {}

    public function registrationStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.', 'trace_id' => app('trace_id')], 401);
        }

        return response()->json([
            'data'     => $this->completeRegistrationProfileService->registrationStatus($user),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function completeAccountType(CompleteAccountTypeRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->completeRegistrationProfileService->completeAccountType($user, (string) $request->validated('account_type'));

        return response()->json([
            'data'     => $this->completeRegistrationProfileService->registrationStatus($user->fresh()),
            'message'  => 'تم حفظ نوع الحساب.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function completeIndividualProfile(CompleteIndividualProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->completeRegistrationProfileService->completeIndividualProfile($user, (string) $request->validated('full_name'));

        return response()->json([
            'data'     => $this->completeRegistrationProfileService->registrationStatus($user->fresh()),
            'message'  => 'تم حفظ البيانات.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function completeCompanyProfile(CompleteCompanyProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $v = $request->validated();
        $this->completeRegistrationProfileService->completeCompanyProfile(
            $user,
            (string) $v['company_name'],
            (string) $v['contact_name'],
        );

        return response()->json([
            'data'     => $this->completeRegistrationProfileService->registrationStatus($user->fresh()),
            'message'  => 'تم إرسال الطلب للمراجعة.',
            'trace_id' => app('trace_id'),
        ]);
    }
}
