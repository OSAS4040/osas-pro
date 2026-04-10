<?php

namespace App\Jobs;

use App\Models\IdempotencyKey;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireIdempotencyKeysJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;
    public int $timeout = 300;
    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        $this->onQueue('low_priority');
    }

    public function handle(): void
    {
        try {
            // Delete in small chunks to avoid long-running transactions under load.
            $deadline = microtime(true) + 90.0;
            do {
                if (microtime(true) >= $deadline) {
                    break;
                }
                $deleted = IdempotencyKey::query()
                    ->where('expires_at', '<', now())
                    ->limit(1000)
                    ->delete();
            } while ($deleted > 0);
        } catch (\Throwable $e) {
            report($e);
            // Keep scheduler resilient in peak windows; retry on next tick.
        }
    }
}
