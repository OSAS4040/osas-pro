<?php

namespace Tests\Feature\Auth;

use App\Jobs\SyncUserPushDeviceJob;
use App\Models\UserPushDevice;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushDeviceTest extends TestCase
{
    public function test_login_dispatches_push_sync_job_when_fcm_token_present(): void
    {
        Queue::fake();
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/auth/login', [
            'email'     => $tenant['user']->email,
            'password'  => 'Password123!',
            'fcm_token' => str_repeat('a', 32),
        ])->assertStatus(200);

        Queue::assertPushed(SyncUserPushDeviceJob::class, function (SyncUserPushDeviceJob $job) use ($tenant): bool {
            return $job->userId === $tenant['user']->id
                && $job->companyId === $tenant['company']->id;
        });
    }

    public function test_push_device_endpoint_dispatches_job(): void
    {
        Queue::fake();
        $tenant = $this->createTenant();
        $plain = $tenant['user']->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/v1/auth/push-device', [
                'fcm_token'   => str_repeat('b', 32),
                'device_name' => 'JUnit',
                'device_type' => 'android',
            ])
            ->assertStatus(200)
            ->assertJsonFragment(['message' => 'تم قبول تسجيل الجهاز للإشعارات.']);

        Queue::assertPushed(SyncUserPushDeviceJob::class);
    }

    public function test_sync_user_push_device_job_persists_row(): void
    {
        $tenant = $this->createTenant();
        $token = str_repeat('c', 32);

        (new SyncUserPushDeviceJob(
            $tenant['user']->id,
            $tenant['company']->id,
            $token,
            'device-x',
            'android',
        ))->handle();

        $this->assertDatabaseHas('user_push_devices', [
            'user_id'    => $tenant['user']->id,
            'company_id' => $tenant['company']->id,
            'fcm_token'  => $token,
        ]);
        $this->assertSame(1, UserPushDevice::query()->where('user_id', $tenant['user']->id)->count());
    }

    public function test_logout_with_fcm_token_removes_matching_push_device_row(): void
    {
        $tenant = $this->createTenant();
        $fcm = str_repeat('d', 32);
        (new SyncUserPushDeviceJob(
            $tenant['user']->id,
            $tenant['company']->id,
            $fcm,
            'handset',
            'android',
        ))->handle();

        $plain = $tenant['user']->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/v1/auth/logout', ['fcm_token' => $fcm])
            ->assertStatus(200);

        $this->assertDatabaseMissing('user_push_devices', ['fcm_token' => $fcm]);
    }

    public function test_logout_all_removes_all_push_devices_for_user(): void
    {
        $tenant = $this->createTenant();
        (new SyncUserPushDeviceJob(
            $tenant['user']->id,
            $tenant['company']->id,
            str_repeat('e', 32),
            'a',
            'android',
        ))->handle();
        (new SyncUserPushDeviceJob(
            $tenant['user']->id,
            $tenant['company']->id,
            str_repeat('f', 32),
            'b',
            'ios',
        ))->handle();

        $this->assertSame(2, UserPushDevice::query()->where('user_id', $tenant['user']->id)->count());

        $plain = $tenant['user']->createToken('api')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$plain)
            ->postJson('/api/v1/auth/logout-all')
            ->assertStatus(200);

        $this->assertSame(0, UserPushDevice::query()->where('user_id', $tenant['user']->id)->count());
    }
}
