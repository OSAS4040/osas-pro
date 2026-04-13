<?php

namespace Tests\Feature\Auth;

use App\Models\UserPushDevice;
use Tests\TestCase;

/**
 * End-to-end API lifecycle (no Flutter): login → push → logout → re-login → logout-all.
 * Simulates FCM "refresh" as a second push registration with a new token (server path).
 */
class AuthMobileApiFlowTest extends TestCase
{
    public function test_end_to_end_login_push_logout_logout_all_and_second_token(): void
    {
        $tenant = $this->createTenant();
        $user = $tenant['user'];

        $fcmLogin = str_repeat('1', 32);
        $fcmRefresh = str_repeat('2', 32);

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier'   => $user->email,
            'password'     => 'Password123!',
            'device_name'  => 'e2e-field',
            'device_type'  => 'android',
            'fcm_token'    => $fcmLogin,
        ]);

        $login->assertStatus(200)
            ->assertJsonPath('token_type', 'Bearer');

        $bearer = (string) $login->json('token');
        $this->assertNotSame('', $bearer);

        $this->assertDatabaseHas('user_push_devices', [
            'user_id'   => $user->id,
            'fcm_token' => $fcmLogin,
        ]);

        // Same as client receiving onTokenRefresh: register new token while session active.
        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->postJson('/api/v1/auth/push-device', [
                'fcm_token'   => $fcmRefresh,
                'device_name' => 'e2e-after-refresh',
                'device_type' => 'android',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('user_push_devices', [
            'user_id'   => $user->id,
            'fcm_token' => $fcmRefresh,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$bearer)
            ->postJson('/api/v1/auth/logout', [
                'fcm_token' => $fcmRefresh,
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('user_push_devices', ['fcm_token' => $fcmRefresh]);
        $this->assertDatabaseHas('user_push_devices', ['fcm_token' => $fcmLogin]);

        $login2 = $this->postJson('/api/v1/auth/login', [
            'identifier'   => $user->email,
            'password'     => 'Password123!',
            'device_name'  => 'e2e-field-2',
            'device_type'  => 'android',
        ])->assertStatus(200);

        $bearer2 = (string) $login2->json('token');

        $this->withHeader('Authorization', 'Bearer '.$bearer2)
            ->postJson('/api/v1/auth/logout-all')
            ->assertStatus(200);

        $this->assertSame(0, UserPushDevice::query()->where('user_id', $user->id)->count());
    }
}
