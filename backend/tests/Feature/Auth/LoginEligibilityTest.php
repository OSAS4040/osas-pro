<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Support\Auth\LoginEligibilityResult;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_00_CLOSURE_REPORT.md — أهلية تسجيل الدخول
 */
#[Group('phase0')]
class LoginEligibilityTest extends TestCase
{
    public function test_active_user_receives_token(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'trace_id']);

        $this->assertGreaterThan(0, PersonalAccessToken::query()->where('tokenable_id', $tenant['user']->id)->count());
    }

    public function test_blocked_user_password_login_returns_403_with_reason_and_no_new_token(): void
    {
        $tenant = $this->createTenant('staff');
        $user = $tenant['user'];
        $before = PersonalAccessToken::query()->where('tokenable_id', $user->id)->count();
        $user->update(['status' => UserStatus::Blocked]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('reason_code', LoginEligibilityResult::REASON_ACCOUNT_BLOCKED)
            ->assertJsonPath('message_key', 'auth.login.account_blocked');

        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame($before, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());
    }

    public function test_suspended_user_returns_account_suspended(): void
    {
        $tenant = $this->createTenant('staff');
        $user = $tenant['user'];
        $user->update(['status' => UserStatus::Suspended]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ])
            ->assertForbidden()
            ->assertJsonPath('reason_code', LoginEligibilityResult::REASON_ACCOUNT_SUSPENDED);
    }

    public function test_inactive_status_returns_account_inactive(): void
    {
        $tenant = $this->createTenant('staff');
        $user = $tenant['user'];
        $user->update(['status' => UserStatus::Inactive, 'is_active' => true]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ])
            ->assertForbidden()
            ->assertJsonPath('reason_code', LoginEligibilityResult::REASON_ACCOUNT_INACTIVE);
    }

    public function test_active_status_but_is_active_false_returns_account_disabled(): void
    {
        $tenant = $this->createTenant('staff');
        $user = $tenant['user'];
        $user->update(['status' => UserStatus::Active, 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password123!',
        ])
            ->assertForbidden()
            ->assertJsonPath('reason_code', LoginEligibilityResult::REASON_ACCOUNT_DISABLED);
    }

    public function test_phone_verify_otp_blocked_user_does_not_issue_token(): void
    {
        User::withoutGlobalScopes()->create([
            'uuid'               => \Illuminate\Support\Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => '966501112233',
            'email'              => null,
            'password'           => bcrypt('secret'),
            'phone'              => '966501112233',
            'phone_verified_at'  => now()->subDay(),
            'role'               => \App\Enums\UserRole::PhoneOnboarding,
            'status'             => UserStatus::Blocked,
            'is_active'          => true,
            'registration_stage' => 'phone_verified',
        ]);

        PhoneOtp::query()->create([
            'phone'         => '966501112233',
            'otp_code_hash' => Hash::make('654321'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        $response = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0501112233',
            'otp'   => '654321',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('reason_code', LoginEligibilityResult::REASON_ACCOUNT_BLOCKED);

        $this->assertArrayNotHasKey('token', $response->json());
    }
}
