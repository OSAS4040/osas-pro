<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResolveLoginContextAction;
use App\Enums\LoginPrincipalKind;
use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformAuditLogger;
use App\Services\Auth\AuthLoginEventRecorder;
use App\Services\Auth\AuthSecurityTelemetryService;
use App\Http\Requests\Auth\PhoneOtpSendRequest;
use App\Http\Requests\Auth\PhoneOtpVerifyRequest;
use App\Services\PhoneRegistration\CompleteRegistrationProfileService;
use App\Services\PhoneRegistration\PhoneOtpService;
use App\Services\PhoneRegistration\PhoneRegistrationTokenIssuer;
use App\Services\PhoneRegistration\RegisterOrLoginByPhoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PhoneOtpAuthController extends Controller
{
    public function __construct(
        private readonly PhoneOtpService $phoneOtpService,
        private readonly RegisterOrLoginByPhoneService $registerOrLoginByPhoneService,
        private readonly PhoneRegistrationTokenIssuer $tokenIssuer,
        private readonly CompleteRegistrationProfileService $completeRegistrationProfileService,
        private readonly ResolveLoginContextAction $resolveLoginContext,
        private readonly AuthLoginEventRecorder $authLoginEventRecorder,
        private readonly AuthSecurityTelemetryService $authSecurityTelemetry,
        private readonly PlatformAuditLogger $platformAuditLogger,
    ) {}

    public function requestOtp(PhoneOtpSendRequest $request): JsonResponse
    {
        try {
            $this->phoneOtpService->requestOtp((string) $request->validated('phone'), $request);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message'  => 'تعذّر إرسال الرمز. حاول لاحقاً.',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        return response()->json([
            'message'  => 'إن كان الرقم صالحاً، سيصلك رمز التحقق قريباً.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function verifyOtp(PhoneOtpVerifyRequest $request): JsonResponse
    {
        $phone = (string) $request->validated('phone');
        $otp   = (string) $request->validated('otp');

        $check = $this->phoneOtpService->verifyOtp($phone, $otp, $request);
        if (! $check['valid']) {
            $phoneNorm = $this->phoneOtpService->normalizedPhone($phone);
            if ($phoneNorm !== '') {
                $this->authSecurityTelemetry->recordInvalidOtpVerification($request, $phoneNorm);
            }

            return $this->phoneOtpVerifyRejectedJson((string) ($check['reason'] ?? ''));
        }

        try {
            $resolved = $this->registerOrLoginByPhoneService->resolveOrCreateUserAfterVerifiedOtp($phone);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message'  => 'تعذّر إكمال التحقق.',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        $user  = $resolved['user'];
        $isNew = $resolved['is_new_user'];

        $resolution = ($this->resolveLoginContext)($user);
        if (! $resolution->eligibility->allowed) {
            Log::info('auth.phone_otp.denied_eligibility', [
                'user_id'     => $user->id,
                'reason_code' => $resolution->eligibility->reasonCode,
                'message_key' => $resolution->eligibility->messageKey,
                'ip'          => $request->ip(),
                'trace_id'    => app('trace_id'),
            ]);

            $this->authLoginEventRecorder->loginDenied(
                $user,
                (string) $resolution->eligibility->reasonCode,
                'otp_phone',
                $request,
            );

            return response()->json($resolution->eligibility->toForbiddenResponseBody('ar'), 403);
        }

        $payload = $this->tokenIssuer->issue($user, $request);
        if ($resolution->accountContext !== null) {
            $payload['account_context'] = $resolution->accountContext->toArray();
            if ($resolution->accountContext->principalKind === LoginPrincipalKind::PlatformEmployee) {
                $this->platformAuditLogger->record($user, 'platform.login', $request, ['channel' => 'otp_phone']);
            }
        }
        $status  = $this->completeRegistrationProfileService->registrationStatus($user);

        return response()->json(array_merge($payload, [
            'needs_account_type'     => $status['needs_account_type'],
            'needs_basic_profile'    => $status['needs_basic_profile'],
            'is_new_user'            => $isNew,
            'company_pending_review' => $status['company_pending_review'],
            'onboarding_active'      => $status['onboarding_active'] ?? true,
        ]));
    }

    private function phoneOtpVerifyRejectedJson(string $reason): JsonResponse
    {
        $locale = app()->getLocale() === 'en' ? 'en' : 'ar';
        $messages = (array) config('auth_security.messages.'.$locale, []);

        [$messageKey, $reasonCode] = match ($reason) {
            'expired' => ['auth.phone.otp_expired', 'OTP_EXPIRED'],
            'not_found' => ['auth.phone.otp_not_found', 'OTP_NOT_FOUND'],
            'locked' => ['auth.phone.otp_locked', 'OTP_VERIFY_LOCKED'],
            'max_attempts' => ['auth.phone.otp_max_attempts', 'OTP_MAX_ATTEMPTS'],
            'invalid_phone' => ['auth.phone.invalid_phone', 'OTP_PHONE_INVALID'],
            default => ['auth.phone.invalid_otp', 'INVALID_OTP'],
        };

        $resolved = $messages[$messageKey] ?? null;
        $message = is_string($resolved) && $resolved !== ''
            ? $resolved
            : (is_string($messages['auth.phone.invalid_otp'] ?? null) ? (string) $messages['auth.phone.invalid_otp'] : 'رمز غير صالح أو منتهٍ.');

        return response()->json([
            'message'     => $message,
            'message_key' => $messageKey,
            'reason_code' => $reasonCode,
            'trace_id'    => app('trace_id'),
        ], 422);
    }
}
