<?php

declare(strict_types=1);

namespace App\Services\PhoneRegistration;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\AuthLoginEventRecorder;
use App\Services\Auth\AuthSessionMetadataWriter;
use App\Services\Auth\LoginBootstrapService;
use Illuminate\Http\Request;

/**
 * يصدر توكن Sanctum + حمولة bootstrap بنفس شكل تسجيل الدخول الحالي.
 */
final class PhoneRegistrationTokenIssuer
{
    public function __construct(
        private readonly LoginBootstrapService $loginBootstrap,
        private readonly AuthSessionMetadataWriter $authSessionMetadataWriter,
        private readonly AuthLoginEventRecorder $authLoginEventRecorder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function issue(User $user, Request $request): array
    {
        $roleValue = (string) $user->getRawOriginal('role');
        $permissions = $this->permissionsForRole($roleValue);
        $abilitiesForToken = $permissions !== [] ? $permissions : ['*'];
        $newAccessToken = $user->createToken('phone-web', $abilitiesForToken);
        $this->authSessionMetadataWriter->apply($newAccessToken->accessToken, $request, 'otp_phone');
        $this->authLoginEventRecorder->loginSuccess($user, $newAccessToken->accessToken, 'otp_phone', $request);
        $token = $newAccessToken->plainTextToken;

        $user->loadMissing('company', 'branch');
        $bootstrap = $this->loginBootstrap->build($user, $permissions);

        return array_merge([
            'token'       => $token,
            'token_type'  => 'Bearer',
            'user'        => $this->formatUser($user),
            'permissions' => $permissions,
            'trace_id'    => app('trace_id'),
        ], $bootstrap);
    }

    /**
     * @return list<string>
     */
    private function permissionsForRole(string $roleKey): array
    {
        $permissions = config('permissions.roles', []);
        $perms = $permissions[$roleKey] ?? [];

        if (in_array('*', $perms, true)) {
            return config('permissions.all_permissions', []);
        }

        return $perms;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatUser(User $user): array
    {
        return [
            'id'                 => $user->id,
            'uuid'               => $user->uuid,
            'name'               => $user->name,
            'email'              => $user->email,
            'phone'              => $user->phone,
            'role'               => $user->getRawOriginal('role'),
            'status'             => $user->getRawOriginal('status'),
            'company_id'         => $user->company_id,
            'branch_id'          => $user->branch_id,
            'customer_id'        => $user->customer_id,
            'is_active'          => $user->is_active,
            'account_type'       => $user->account_type,
            'registration_stage' => $user->registration_stage,
            'company'            => $user->relationLoaded('company') ? $user->company : null,
            'branch'             => $user->relationLoaded('branch') ? $user->branch : null,
            'subscription'       => $user->company_id ? $this->subscriptionBillingSummary((int) $user->company_id) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionBillingSummary(int $companyId): array
    {
        $row = Subscription::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return [
                'plan'            => null,
                'status'          => null,
                'ends_at'         => null,
                'grace_ends_at'   => null,
                'billing_state'   => 'none',
                'max_branches'    => null,
                'max_users'       => null,
                'grace_read_only' => false,
            ];
        }

        $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
        $now    = now();
        $endsAt = $row->ends_at;
        $grace  = $row->grace_ends_at;

        $expiredByTime = $endsAt !== null && $endsAt->lt($now);
        $inGraceWindow  = $expiredByTime && $grace !== null && $now->lt($grace);

        if ($status === 'suspended') {
            $billingState = 'suspended';
        } elseif (! $expiredByTime) {
            $billingState = 'active';
        } elseif ($inGraceWindow) {
            $billingState = 'grace';
        } else {
            $billingState = 'expired';
        }

        return [
            'plan'            => $row->plan,
            'status'          => $status,
            'ends_at'         => $row->ends_at?->toIso8601String(),
            'grace_ends_at'   => $row->grace_ends_at?->toIso8601String(),
            'billing_state'   => $billingState,
            'max_branches'    => (int) ($row->max_branches ?? 1),
            'max_users'       => (int) ($row->max_users ?? 5),
            'grace_read_only' => $billingState === 'grace',
        ];
    }
}
