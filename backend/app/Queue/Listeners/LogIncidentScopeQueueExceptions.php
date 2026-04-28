<?php

namespace App\Queue\Listeners;

use App\Jobs\ExpireIdempotencyKeysJob;
use App\Jobs\ExpireInventoryReservationsJob;
use App\Jobs\NotifyCustomerWorkOrderWhatsAppJob;
use App\Jobs\PostPosLedgerJob;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Logs the real exception on each failed attempt (before MaxAttemptsExceeded overwrites visibility).
 * Scoped to high-churn / incident-prone jobs only to avoid log noise.
 */
final class LogIncidentScopeQueueExceptions
{
    /** @var list<class-string> */
    private const WATCHLIST = [
        PostPosLedgerJob::class,
        ExpireInventoryReservationsJob::class,
        ExpireIdempotencyKeysJob::class,
        NotifyCustomerWorkOrderWhatsAppJob::class,
    ];

    public function __invoke(JobExceptionOccurred $event): void
    {
        $resolved = $event->job->resolveName();
        if (! in_array($resolved, self::WATCHLIST, true)) {
            return;
        }

        $e = $event->exception;
        $prev = $e->getPrevious();
        $payload = $event->job->payload();

        Log::warning('queue.job.exception_occurred', [
            'job_class'           => $resolved,
            'connection'        => $event->connectionName,
            'queue'             => $event->job->getQueue(),
            'attempt'           => $event->job->attempts(),
            'payload_uuid'      => $payload['uuid'] ?? null,
            'payload_preview'   => Str::limit((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 4000),
            'exception_class'   => $e::class,
            'exception_message' => $e->getMessage(),
            'previous_class'    => $prev ? $prev::class : null,
            'previous_message'  => $prev?->getMessage(),
            'trace_head'        => Str::limit($e->getTraceAsString(), 6000),
        ]);
    }
}
