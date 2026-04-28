<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\PhoneOtp;
use App\Models\RegistrationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_00_CLOSURE_REPORT.md — تسجيل بالهاتف / OTP
 */
#[Group('phase0')]
class PhoneRegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_otp_returns_generic_message(): void
    {
        $this->postJson('/api/v1/auth/phone/request-otp', [
            'phone' => '0500112233',
        ])->assertOk()
            ->assertJsonStructure(['message', 'trace_id']);

        $this->assertDatabaseHas('phone_otps', [
            'phone' => '966500112233',
        ]);
    }

    public function test_request_otp_rejects_invalid_phone(): void
    {
        $this->postJson('/api/v1/auth/phone/request-otp', [
            'phone' => 'xyz',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_request_otp_rate_limited_per_phone_cache(): void
    {
        config(['saas.phone_otp_send_max_per_phone_window' => 2]);
        Cache::flush();

        $this->postJson('/api/v1/auth/phone/request-otp', ['phone' => '0500998877'])->assertOk();
        $this->postJson('/api/v1/auth/phone/request-otp', ['phone' => '0500998877'])->assertOk();

        $this->postJson('/api/v1/auth/phone/request-otp', ['phone' => '0500998877'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        PhoneOtp::query()->create([
            'phone'         => '966500554433',
            'otp_code_hash' => Hash::make('888777'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->subMinute(),
            'max_attempts'  => 8,
        ]);

        $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500554433',
            'otp'   => '888777',
        ])->assertStatus(422)
            ->assertJsonPath('message_key', 'auth.phone.otp_expired')
            ->assertJsonPath('reason_code', 'OTP_EXPIRED');
    }

    public function test_verify_otp_existing_user_is_not_new(): void
    {
        User::withoutGlobalScopes()->create([
            'uuid'               => \Illuminate\Support\Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => '966500778899',
            'email'              => null,
            'password'           => bcrypt('secret'),
            'phone'              => '966500778899',
            'phone_verified_at'  => now()->subDay(),
            'role'               => UserRole::PhoneOnboarding,
            'status'             => 'active',
            'is_active'          => true,
            'registration_stage' => 'account_type_selected',
            'account_type'       => 'individual',
        ]);

        PhoneOtp::query()->create([
            'phone'         => '966500778899',
            'otp_code_hash' => Hash::make('909090'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500778899',
            'otp'   => '909090',
        ])->assertOk()
            ->assertJsonPath('is_new_user', false)
            ->assertJsonStructure(['account_context', 'token'])
            ->assertJsonPath('account_context.guard_hint', 'onboarding');
    }

    public function test_verify_otp_rejects_invalid_code(): void
    {
        PhoneOtp::query()->create([
            'phone'         => '966500112233',
            'otp_code_hash' => Hash::make('123456'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500112233',
            'otp'   => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('message_key', 'auth.phone.invalid_otp')
            ->assertJsonPath('reason_code', 'INVALID_OTP');
    }

    public function test_verify_otp_creates_new_user_and_profile(): void
    {
        PhoneOtp::query()->create([
            'phone'         => '966500112233',
            'otp_code_hash' => Hash::make('654321'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        $res = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500112233',
            'otp'   => '654321',
        ]);

        $res->assertOk()
            ->assertJsonPath('is_new_user', true)
            ->assertJsonPath('needs_account_type', true)
            ->assertJsonPath('needs_basic_profile', true);

        $this->assertDatabaseHas('users', [
            'phone' => '966500112233',
            'role'  => UserRole::PhoneOnboarding->value,
        ]);

        $user = User::withoutGlobalScopes()->where('phone', '966500112233')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('registration_profiles', [
            'user_id' => $user->id,
            'status'  => 'draft',
        ]);
    }

    public function test_cannot_reuse_verified_otp(): void
    {
        PhoneOtp::query()->create([
            'phone'         => '966500112233',
            'otp_code_hash' => Hash::make('111222'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500112233',
            'otp'   => '111222',
        ])->assertOk();

        $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500112233',
            'otp'   => '111222',
        ])->assertStatus(422)
            ->assertJsonPath('message_key', 'auth.phone.otp_not_found')
            ->assertJsonPath('reason_code', 'OTP_NOT_FOUND');
    }

    public function test_complete_flow_individual(): void
    {
        $user = User::withoutGlobalScopes()->create([
            'uuid'               => \Illuminate\Support\Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => '966500112233',
            'email'              => null,
            'password'           => bcrypt('x'),
            'phone'              => '966500112233',
            'phone_verified_at'  => now(),
            'role'               => UserRole::PhoneOnboarding,
            'status'             => 'active',
            'is_active'          => true,
            'registration_stage' => 'phone_verified',
        ]);
        RegistrationProfile::query()->create([
            'user_id'                   => $user->id,
            'status'                    => 'draft',
            'company_activation_status' => 'not_applicable',
        ]);

        $token = $user->createToken('t', ['phone_registration.flow'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/complete-account-type', [
                'account_type' => 'individual',
            ])->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/complete-individual-profile', [
                'full_name' => 'أحمد الفردي',
            ])->assertOk();

        $user->refresh();
        $this->assertSame('individual_completed', $user->registration_stage);
        $this->assertSame('أحمد الفردي', $user->name);
    }

    public function test_company_submission_sets_pending_review(): void
    {
        $user = User::withoutGlobalScopes()->create([
            'uuid'               => \Illuminate\Support\Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'name'               => '966500112233',
            'email'              => null,
            'password'           => bcrypt('x'),
            'phone'              => '966500112233',
            'phone_verified_at'  => now(),
            'role'               => UserRole::PhoneOnboarding,
            'status'             => 'active',
            'is_active'          => true,
            'registration_stage' => 'phone_verified',
            'account_type'       => 'company',
        ]);
        RegistrationProfile::query()->create([
            'user_id'                   => $user->id,
            'status'                    => 'draft',
            'company_activation_status' => 'not_applicable',
            'account_type'              => 'company',
        ]);

        $token = $user->createToken('t', ['phone_registration.flow'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/complete-company-profile', [
                'company_name' => 'ورشة الاختبار',
                'contact_name' => 'مسؤول الاتصال',
            ])->assertOk();

        $user->refresh();
        $this->assertSame('company_pending_review', $user->registration_stage);

        $p = RegistrationProfile::query()->where('user_id', $user->id)->first();
        $this->assertSame('pending_review', $p->company_activation_status);
    }
}
