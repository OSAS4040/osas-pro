<?php

namespace Tests\Feature\PreProduction;

use App\Jobs\ExpireIdempotencyKeysJob;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * ضغط خفيف على مسار jobs (مع QUEUE_CONNECTION=sync في PHPUnit يتم تنفيذ المعالجات مباشرة).
 * للاختبار على Redis الحقيقي راجع قائمة التشغيل اليدوي في تعليق Makefile target test-preprod.
 */
#[Group('pre-production')]
class QueueJobSoakTest extends TestCase
{
    public function test_expire_idempotency_job_handler_runs_many_times_without_error(): void
    {
        $failuresForJob = static fn (): int => (int) DB::table('failed_jobs')
            ->where('payload', 'like', '%ExpireIdempotencyKeysJob%')
            ->count();

        $failedBefore = $failuresForJob();

        $job = new ExpireIdempotencyKeysJob;

        for ($i = 0; $i < 60; $i++) {
            $job->handle();
        }

        $this->assertSame($failedBefore, $failuresForJob(), 'ExpireIdempotencyKeysJob runs must not enqueue new failed_jobs rows for this job');
    }
}
