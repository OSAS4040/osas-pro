<?php

declare(strict_types=1);

namespace App\Services\PhoneRegistration;

use App\Enums\UserRole;
use App\Models\RegistrationProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * مسار التسجيل بالجوال — إكمال الملف فقط (لا شركة تشغيلية قبل مراجعة المنصة للشركات).
 *
 * سياسة المنتج للمستخدم الفرد بعد `individual_completed`:
 * — يبقى الدور `phone_onboarding` و`company_id` فارغاً؛ لا يُمنح نطاق مستأجر تشغيلي.
 * — الواجهة تعرض شاشة ترحيب/إرشاد (PhoneOnboardingDoneView) مع مسارات صريحة: تسجيل شركة جديدة
 *   عبر `/register` (بعد تسجيل الخروج)، أو الدخول بحساب شركة قائم عبر `/login`، دون إنشاء كيانات مالية تلقائياً.
 */
final class CompleteRegistrationProfileService
{
    public function assertPhoneOnboarding(User $user): void
    {
        if ((string) $user->getRawOriginal('role') !== UserRole::PhoneOnboarding->value) {
            throw ValidationException::withMessages([
                'user' => ['هذا المسار غير متاح لهذا الحساب.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationStatus(User $user): array
    {
        if ((string) $user->getRawOriginal('role') !== UserRole::PhoneOnboarding->value) {
            return [
                'onboarding_active'          => false,
                'registration_stage'         => $user->registration_stage,
                'account_type'               => $user->account_type,
                'profile'                    => null,
                'needs_account_type'         => false,
                'needs_basic_profile'        => false,
                'company_pending_review'     => false,
                'profile_completion_percent' => 0,
            ];
        }

        $profile = RegistrationProfile::query()->where('user_id', $user->id)->first();

        return [
            'onboarding_active'          => true,
            'registration_stage'         => $user->registration_stage,
            'account_type'               => $user->account_type,
            'profile'                    => $profile?->toArray(),
            'needs_account_type'         => $user->account_type === null,
            'needs_basic_profile'        => $this->needsBasicProfile($user, $profile),
            'company_pending_review'     => $profile !== null
                && $profile->company_activation_status === 'pending_review',
            'profile_completion_percent' => $profile?->profile_completion_percent ?? 0,
        ];
    }

    public function completeAccountType(User $user, string $accountType): void
    {
        $this->assertPhoneOnboarding($user);
        if (! in_array($accountType, ['individual', 'company'], true)) {
            throw ValidationException::withMessages(['account_type' => ['نوع الحساب غير صالح.']]);
        }
        if ($user->account_type !== null) {
            throw ValidationException::withMessages(['account_type' => ['تم اختيار نوع الحساب مسبقاً.']]);
        }

        DB::transaction(function () use ($user, $accountType): void {
            $user->forceFill([
                'account_type'       => $accountType,
                'registration_stage' => 'account_type_set',
            ])->save();

            $p = RegistrationProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['status' => 'draft', 'company_activation_status' => 'not_applicable'],
            );
            $p->forceFill([
                'account_type'               => $accountType,
                'profile_completion_percent' => 30,
            ])->save();
        });
    }

    public function completeIndividualProfile(User $user, string $fullName): void
    {
        $this->assertPhoneOnboarding($user);
        if ($user->account_type !== 'individual') {
            throw ValidationException::withMessages(['account_type' => ['يجب اختيار حساب فردي أولاً.']]);
        }
        if ($user->registration_stage === 'individual_completed') {
            throw ValidationException::withMessages(['profile' => ['تم إكمال الملف مسبقاً.']]);
        }

        DB::transaction(function () use ($user, $fullName): void {
            $user->forceFill([
                'name'                 => $fullName,
                'registration_stage'   => 'individual_completed',
                'profile_completed_at' => now(),
            ])->save();

            $p = RegistrationProfile::query()->where('user_id', $user->id)->firstOrFail();
            $p->forceFill([
                'full_name'                  => $fullName,
                'status'                     => 'active',
                'company_activation_status'  => 'not_applicable',
                'profile_completion_percent' => 100,
            ])->save();
        });
    }

    public function completeCompanyProfile(User $user, string $companyName, string $contactName): void
    {
        $this->assertPhoneOnboarding($user);
        if ($user->account_type !== 'company') {
            throw ValidationException::withMessages(['account_type' => ['يجب اختيار حساب شركة أولاً.']]);
        }
        if ($user->registration_stage === 'company_pending_review') {
            throw ValidationException::withMessages(['profile' => ['تم إرسال الطلب مسبقاً.']]);
        }

        DB::transaction(function () use ($user, $companyName, $contactName): void {
            $user->forceFill([
                'name'                 => $contactName,
                'registration_stage'   => 'company_pending_review',
                'profile_completed_at' => null,
            ])->save();

            $p = RegistrationProfile::query()->where('user_id', $user->id)->firstOrFail();
            $p->forceFill([
                'company_name'               => $companyName,
                'contact_name'               => $contactName,
                'status'                     => 'pending_review',
                'company_activation_status'  => 'pending_review',
                'profile_completion_percent' => 60,
                'submitted_at'               => now(),
            ])->save();
        });
    }

    private function needsBasicProfile(User $user, ?RegistrationProfile $profile): bool
    {
        if ($user->account_type === null) {
            return true;
        }
        if ($user->account_type === 'individual') {
            return $user->registration_stage !== 'individual_completed';
        }
        if ($user->account_type === 'company') {
            return $user->registration_stage !== 'company_pending_review';
        }

        return true;
    }
}
