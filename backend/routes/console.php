<?php

use App\Services\Platform\PlatformAdminOverviewService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ExpireIdempotencyKeysJob;
use App\Jobs\CheckSubscriptionStatusJob;
use App\Jobs\ExpireInventoryReservationsJob;
use App\Jobs\DispatchWebhookJob;
use App\Jobs\SendDocumentExpiryNotificationsJob;
use App\Jobs\SendSupplierContractExpiryNotificationsJob;
use App\Jobs\SubscriptionsV2\PruneSubscriptionRealtimeEventsJob;
use App\Jobs\SubscriptionsV2\RunSubscriptionRenewalJob;

/**
 * During RC/peak diagnostics we can isolate recurring housekeeping jobs
 * to avoid contaminating write-path latency measurements.
 */
$isolateRecurringForRc = filter_var((string) env('RC_ISOLATE_RECURRING_JOBS', false), FILTER_VALIDATE_BOOL);

/**
 * Incident containment: stop scheduler from enqueueing high-churn housekeeping jobs only.
 * Does not affect financial flows (e.g. PostPosLedgerJob is not scheduled here).
 */
$containHousekeeping = filter_var((string) env('INCIDENT_CONTAIN_HOUSEKEEPING_JOBS', false), FILTER_VALIDATE_BOOL);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (! $isolateRecurringForRc && ! $containHousekeeping) {
    Schedule::job(new ExpireIdempotencyKeysJob, 'low_priority')
        ->hourly()
        ->withoutOverlapping(30);
}
Schedule::job(new CheckSubscriptionStatusJob, 'default')->dailyAt('00:05');
Schedule::job(new RunSubscriptionRenewalJob, 'high')->dailyAt('00:15');
Schedule::job(new PruneSubscriptionRealtimeEventsJob, 'low')->dailyAt('02:30')->withoutOverlapping(30);
if (! $isolateRecurringForRc && ! $containHousekeeping) {
    Schedule::job(new ExpireInventoryReservationsJob, 'low_priority')
        ->everyFifteenMinutes()
        ->withoutOverlapping(30);
}
if (! $isolateRecurringForRc) {
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

// نبض آخر تشغيل للجدولة — يُقرأ في ملخص مشغّل المنصة (صحة التشغيل)
Schedule::call(function (): void {
    Cache::put(
        PlatformAdminOverviewService::SCHEDULER_LAST_RUN_CACHE_KEY,
        now()->toIso8601String(),
        now()->addDays(14),
    );
})->everyMinute()->name('platform.schedule_run_heartbeat');

