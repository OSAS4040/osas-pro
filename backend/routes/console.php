<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ExpireIdempotencyKeysJob;
use App\Jobs\CheckSubscriptionStatusJob;
use App\Jobs\ExpireInventoryReservationsJob;
use App\Jobs\DispatchWebhookJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ExpireIdempotencyKeysJob, 'low_priority')->hourly();
Schedule::job(new CheckSubscriptionStatusJob, 'default')->dailyAt('00:05');
Schedule::job(new ExpireInventoryReservationsJob, 'default')->everyFifteenMinutes();

// Retry failed webhook deliveries that are past their next_attempt_at window
Schedule::call(function () {
    $failed = \App\Models\WebhookDelivery::where('status', 'pending')
        ->where('next_attempt_at', '<=', now())
        ->where('attempt', '<', 3)
        ->get();

    foreach ($failed as $delivery) {
        DispatchWebhookJob::dispatch($delivery->id, $delivery->trace_id ?? 'retry-sweep')
            ->onQueue('default');
    }
})->everyFiveMinutes()->name('webhook.retry-sweep');

