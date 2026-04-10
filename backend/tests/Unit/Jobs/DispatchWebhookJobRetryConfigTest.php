<?php

namespace Tests\Unit\Jobs;

use App\Jobs\DispatchWebhookJob;
use PHPUnit\Framework\TestCase;

/**
 * Ensures webhook job follows standardized queue retry policy (no infinite loops).
 */
class DispatchWebhookJobRetryConfigTest extends TestCase
{
    public function test_webhook_job_uses_three_tries_and_backoff(): void
    {
        $job = new DispatchWebhookJob(1, 'trace-test');

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);
    }
}
