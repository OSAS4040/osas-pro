<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ExpireIdempotencyKeysJob;
use App\Jobs\CheckSubscriptionStatusJob;
use App\Jobs\ExpireInventoryReservationsJob;
use App\Jobs\DispatchWebhookJob;
use App\Jobs\SendDocumentExpiryNotificationsJob;
use App\Jobs\SendSupplierContractExpiryNotificationsJob;

/**
 * During RC/peak diagnostics we can isolate recurring housekeeping jobs
 * to avoid contaminating write-path latency measurements.
 */
$isolateRecurringForRc = filter_var((string) env('RC_ISOLATE_RECURRING_JOBS', false), FILTER_VALIDATE_BOOL);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (! $isolateRecurringForRc) {
    Schedule::job(new ExpireIdempotencyKeysJob, 'low_priority')
        ->hourly()
        ->withoutOverlapping(30);
}
Schedule::job(new CheckSubscriptionStatusJob, 'low_priority')->dailyAt('00:05');
if (! $isolateRecurringForRc) {
    Schedule::job(new ExpireInventoryReservationsJob, 'low_priority')
        ->everyFifteenMinutes()
        ->withoutOverlapping(30);
    Schedule::job(new SendDocumentExpiryNotificationsJob, 'default')->dailyAt('07:30');
    Schedule::job(new SendSupplierContractExpiryNotificationsJob, 'default')
        ->dailyAt('07:40')
        ->withoutOverlapping(30);
}

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

// Wave 3 Batch-1: daily financial reconciliation foundation
Schedule::command('finance:reconcile-daily --out-file=reports/financial-reliability/reconciliation-report.json')
    ->dailyAt('01:10')
    ->withoutOverlapping(30)
    ->name('finance.reconcile-daily');

