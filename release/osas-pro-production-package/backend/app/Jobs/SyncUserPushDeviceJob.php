<?php

namespace App\Jobs;

use App\Models\UserPushDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persists FCM registration; queued so login latency stays predictable.
 */
class SyncUserPushDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $companyId,
        public string $fcmToken,
        public ?string $deviceName,
        public ?string $deviceType,
    ) {}

    public function handle(): void
    {
        $token = trim($this->fcmToken);
        if ($token === '' || strlen($token) > 512) {
            return;
        }

        DB::transaction(function () use ($token): void {
            UserPushDevice::query()->updateOrCreate(
                ['fcm_token' => $token],
                [
                    'user_id'            => $this->userId,
                    'company_id'         => $this->companyId,
                    'device_name'        => $this->deviceName,
                    'device_type'        => $this->deviceType,
                    'last_registered_at' => now(),
                ]
            );
        });

        Log::info('push_device.synced', [
            'user_id'  => $this->userId,
            'company_id' => $this->companyId,
            'suffix'   => substr($token, -8),
            'trace_id' => app('trace_id'),
        ]);
    }
}
