<?php

declare(strict_types=1);

namespace App\Services\PhoneRegistration;

use App\Enums\BranchStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\RegistrationProfile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * اعتماد طلب شركة بعد مراجعة المنصة — ينشئ شركة/فرعاً/اشتراكاً تجريبياً ويربط المالك.
 * لا يضبط vertical أو نموذج مالي متقدم (يبقى للواجهات الحالية لاحقاً).
 */
final class ApprovePhoneCompanyRegistrationService
{
    public function approve(RegistrationProfile $profile, User $reviewer): void
    {
        if ($profile->company_activation_status !== 'pending_review') {
            throw ValidationException::withMessages(['profile' => ['هذا الطلب ليس قيد المراجعة.']]);
        }

        $user = $profile->user;
        if (! $user || (string) $user->getRawOriginal('role') !== UserRole::PhoneOnboarding->value) {
            throw ValidationException::withMessages(['user' => ['مستخدم غير صالح لهذا الطلب.']]);
        }

        DB::transaction(function () use ($profile, $user, $reviewer): void {
            $company = Company::query()->create([
                'uuid'      => Str::uuid(),
                'name'      => (string) $profile->company_name,
                'currency'  => 'SAR',
                'timezone'  => 'Asia/Riyadh',
                'status'    => 'active',
                'is_active' => true,
            ]);

            $branch = Branch::query()->withoutGlobalScope('tenant')->create([
                'uuid'       => Str::uuid(),
                'company_id' => $company->id,
                'name'       => 'Main Branch',
                'name_ar'    => 'الفرع الرئيسي',
                'code'       => 'MAIN',
                'status'     => BranchStatus::Active,
                'is_main'    => true,
                'is_active'  => true,
            ]);

            Subscription::query()->withoutGlobalScope('tenant')->create([
                'uuid'         => Str::uuid(),
                'company_id'   => $company->id,
                'plan'         => 'trial',
                'status'       => SubscriptionStatus::Active,
                'starts_at'    => now(),
                'ends_at'      => now()->addDays(14),
                'amount'       => 0,
                'max_branches' => 1,
                'max_users'    => 3,
            ]);

            $user->forceFill([
                'company_id'         => $company->id,
                'branch_id'          => $branch->id,
                'role'               => UserRole::Owner,
                'registration_stage' => 'operational',
            ])->save();

            $profile->forceFill([
                'status'                     => 'active',
                'company_activation_status'  => 'approved',
                'profile_completion_percent' => 100,
                'reviewed_at'                => now(),
                'reviewed_by'                => $reviewer->id,
            ])->save();
        });
    }

    public function reject(RegistrationProfile $profile, User $reviewer, ?string $notes = null): void
    {
        $profile->forceFill([
            'status'                    => 'rejected',
            'company_activation_status' => 'rejected',
            'reviewed_at'               => now(),
            'reviewed_by'               => $reviewer->id,
            'internal_notes'            => $notes,
        ])->save();
    }

    public function requestMoreInfo(RegistrationProfile $profile, User $reviewer, string $notes): void
    {
        $profile->forceFill([
            'company_activation_status' => 'needs_more_info',
            'reviewed_at'               => now(),
            'reviewed_by'               => $reviewer->id,
            'internal_notes'            => $notes,
        ])->save();
    }

    public function suspend(RegistrationProfile $profile, User $reviewer, ?string $notes = null): void
    {
        $profile->forceFill([
            'status'                    => 'suspended',
            'company_activation_status' => 'rejected',
            'reviewed_at'               => now(),
            'reviewed_by'               => $reviewer->id,
            'internal_notes'            => $notes,
        ])->save();

        $user = $profile->user;
        if ($user) {
            $user->forceFill(['is_active' => false])->save();
        }
    }
}
