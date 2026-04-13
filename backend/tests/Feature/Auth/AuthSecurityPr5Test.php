<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuthSuspiciousLoginSignal;
use App\Models\PhoneOtp;
use App\Services\Auth\AuthSecurityTelemetryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityPr5Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_login_throttle_returns_rate_limited_contract_and_no_token(): void
    {
        Config::set('auth_security.login.per_minute', 3);
        $tenant = $this->createTenant('owner');
        $email = $tenant['user']->email;

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => $email,
                'password' => 'WrongPassword!',
            ])->assertUnauthorized();
        }

        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => $email,
            'password' => 'WrongPassword!',
        ]);

        $res->assertStatus(429)
            ->assertJsonPath('message_key', 'auth.security.rate_limited')
            ->assertJsonPath('reason_code', 'RATE_LIMITED');
        $this->assertArrayNotHasKey('token', $res->json());

        $this->assertSame(
            1,
            AuthSuspiciousLoginSignal::query()->where('signal_type', 'rate_limited_login')->count(),
        );
    }

    public function test_verify_otp_throttle_returns_429_without_token(): void
    {
        Config::set('auth_security.otp_verify.per_minute', 2);

        PhoneOtp::query()->create([
            'phone'         => '966500112233',
            'otp_code_hash' => Hash::make('123456'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/phone/verify-otp', [
                'phone' => '0500112233',
                'otp'   => '000000',
            ])->assertStatus(422);
        }

        $res = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '0500112233',
            'otp'   => '000000',
        ]);

        $res->assertStatus(429)
            ->assertJsonPath('message_key', 'auth.security.rate_limited')
            ->assertJsonPath('reason_code', 'RATE_LIMITED');
        $this->assertArrayNotHasKey('token', $res->json());

        $this->assertSame(
            1,
            AuthSuspiciousLoginSignal::query()->where('signal_type', 'rate_limited_otp_verify')->count(),
        );
    }

    public function test_request_otp_route_throttle_returns_429(): void
    {
        Config::set('auth_security.otp_resend.per_minute', 2);

        $this->postJson('/api/v1/auth/phone/request-otp', ['phone' => '0500998877'])->assertOk();
        $this->postJson('/api/v1/auth/phone/request-otp', ['phone' => '0500998877'])->assertOk();

        $res = $this->postJson('/api/v1/auth/phone/request-otp', ['phone' => '0500998877']);

        $res->assertStatus(429)
            ->assertJsonPath('message_key', 'auth.security.rate_limited')
            ->assertJsonPath('reason_code', 'RATE_LIMITED');

        $this->assertSame(
            1,
            AuthSuspiciousLoginSignal::query()->where('signal_type', 'rate_limited_otp_resend')->count(),
        );
    }

    public function test_failed_password_attempts_emit_burst_signal_at_threshold(): void
    {
        Config::set('auth_security.failed_password_login.burst_signal_after_attempts', 3);
        Config::set('auth_security.login.per_minute', 60);

        $tenant = $this->createTenant('owner');
        $email = $tenant['user']->email;

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email'    => $email,
                'password' => 'WrongPassword!',
            ])->assertUnauthorized();
        }

        $this->assertSame(
            1,
            AuthSuspiciousLoginSignal::query()->where('signal_type', 'failed_login_burst')->count(),
        );
    }

    public function test_failed_otp_verify_attempts_emit_burst_signal_at_threshold(): void
    {
        Config::set('auth_security.failed_otp_verify.burst_signal_after_attempts', 2);
        Config::set('auth_security.otp_verify.per_minute', 60);

        PhoneOtp::query()->create([
            'phone'         => '966500112233',
            'otp_code_hash' => Hash::make('123456'),
            'purpose'       => 'phone_register_login',
            'expires_at'    => now()->addMinutes(5),
            'max_attempts'  => 8,
        ]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/phone/verify-otp', [
                'phone' => '0500112233',
                'otp'   => '000000',
            ])->assertStatus(422);
        }

        $this->assertSame(
            1,
            AuthSuspiciousLoginSignal::query()->where('signal_type', 'failed_otp_verify_burst')->count(),
        );
    }

    public function test_failed_password_attempt_counter_tracked_in_cache(): void
    {
        Config::set('auth_security.failed_password_login.burst_signal_after_attempts', 99);
        Config::set('auth_security.login.per_minute', 60);

        $tenant = $this->createTenant('owner');
        $email = $tenant['user']->email;

        $this->postJson('/api/v1/auth/login', [
            'email'    => $email,
            'password' => 'WrongPassword!',
        ])->assertUnauthorized();

        $req = Request::create('/api/v1/auth/login', 'POST', [
            'email'    => $email,
            'password' => 'WrongPassword!',
        ]);
        $req->server->set('REMOTE_ADDR', '127.0.0.1');

        $telemetry = app(AuthSecurityTelemetryService::class);
        $state = $telemetry->debugPasswordFailureState($req);
        $this->assertSame(1, $state['count']);
    }

    public function test_invalid_credentials_response_has_no_token(): void
    {
        $tenant = $this->createTenant('owner');

        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'WrongPassword!',
        ]);

        $res->assertUnauthorized()
            ->assertJsonPath('message_key', 'auth.login.invalid_credentials');
        $this->assertArrayNotHasKey('token', $res->json());
    }

    public function test_pr5_does_not_break_login_me_and_sessions(): void
    {
        $tenant = $this->createTenant('owner');

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $login->assertOk()
            ->assertJsonStructure(['token', 'account_context', 'trace_id']);

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data', 'account_context', 'trace_id']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk();
    }

    public function test_internal_suspicious_login_signals_endpoint_returns_json_list(): void
    {
        $tenant = $this->createTenant('owner');

        $token = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/internal/auth/suspicious-login-signals')
            ->assertOk()
            ->assertJsonStructure(['data', 'trace_id']);
    }
}
