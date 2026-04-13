<?php

declare(strict_types=1);

namespace App\Services\PhoneRegistration;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\RegistrationProfile;
use App\Models\User;
use App\Support\Auth\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class RegisterOrLoginByPhoneService
{
    /**
     * @return array{user: User, is_new_user: bool}
     */
    public function resolveOrCreateUserAfterVerifiedOtp(string $phoneRaw): array
    {
        $phone = PhoneNormalizer::normalizeForStorage($phoneRaw);

        return DB::transaction(function () use ($phone): array {
            /** @var User|null $existing */
            $existing = User::withoutGlobalScopes()
                ->where('phone', $phone)
                ->orderBy('id')
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'phone_verified_at' => now(),
                    'last_login_at'     => now(),
                ])->save();

                $this->ensureProfile($existing);

                return ['user' => $existing->fresh(), 'is_new_user' => false];
            }

            $user = User::withoutGlobalScopes()->create([
                'uuid'                => Str::uuid(),
                'company_id'          => null,
                'branch_id'           => null,
                'org_unit_id'         => null,
                'name'                => $phone,
                'email'               => null,
                'password'            => Hash::make(Str::random(64)),
                'phone'               => $phone,
                'phone_verified_at'   => now(),
                'role'                => UserRole::PhoneOnboarding,
                'status'              => UserStatus::Active,
                'is_active'           => true,
                'registration_stage'  => 'phone_verified',
            ]);

            RegistrationProfile::query()->create([
                'user_id'                   => $user->id,
                'status'                    => 'draft',
                'company_activation_status' => 'not_applicable',
                'profile_completion_percent'=> 10,
            ]);

            return ['user' => $user->fresh(), 'is_new_user' => true];
        });
    }

    private function ensureProfile(User $user): void
    {
        RegistrationProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'status'                     => 'draft',
                'company_activation_status'  => 'not_applicable',
                'profile_completion_percent' => 10,
            ],
        );
    }
}
