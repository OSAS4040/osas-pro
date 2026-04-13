<?php

namespace App\Services;

use App\Intelligence\Contracts\DomainEventInterface;
use App\Models\DomainEvent as DomainEventModel;
use App\Models\EventRecordFailure;
use App\Support\Intelligent\TraceContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Persists domain events for Phase 1 (audit / future intelligence).
 *
 * SAFETY: Never throws to callers — failures are logged and optionally stored in
 * `event_record_failures`. Core financial success must never depend on this service.
 */
class DomainEventRecorder
{
    public function record(DomainEventInterface $event): void
    {
        if (! config('intelligent.events.enabled')) {
            return;
        }

        try {
            $this->doRecord($event);
        } catch (\Throwable $e) {
            $this->handleFailure($event, $e);
        }
    }

    /**
     * @param  array<int, DomainEventInterface>  $events
     */
    public function recordMany(array $events): void
    {
        foreach ($events as $event) {
            $this->record($event);
        }
    }

    private function doRecord(DomainEventInterface $event): void
    {
        $traceId       = TraceContext::traceIdForEvent();
        $correlationId = TraceContext::correlationId();
        $meta          = $event->metadata();
        $payload       = $event->payload();

        $companyId = $meta['company_id'] ?? null;
        $branchId  = $meta['branch_id'] ?? null;
        $userId    = $meta['caused_by_user_id'] ?? null;

        $logPayload = [
            'event_name'      => $event->name(),
            'aggregate_type'  => $event->aggregateType(),
            'aggregate_id'    => $event->aggregateId(),
            'company_id'      => $companyId,
            'trace_id'        => $traceId,
            'correlation_id'  => $correlationId,
            'caused_by_user_id' => $userId,
            'result'          => 'recorded',
        ];

        if (config('intelligent.observability.enabled')) {
            Log::info('intelligent.domain_event', $logPayload);
        }

        if (! config('intelligent.events.persist.enabled')) {
            return;
        }

        DomainEventModel::create([
            'uuid'              => (string) Str::uuid(),
            'company_id'        => $companyId,
            'branch_id'         => $branchId,
            'aggregate_type'    => $event->aggregateType(),
            'aggregate_id'      => $event->aggregateId(),
            'event_name'        => $event->name(),
            'event_version'     => $event->eventVersion(),
            'payload_json'      => $payload,
            'metadata_json'     => array_merge($meta, [
                'trace_id'       => $traceId,
                'correlation_id' => $correlationId,
            ]),
            'trace_id'          => $traceId,
            'correlation_id'    => $correlationId,
            'caused_by_user_id' => $userId,
            'caused_by_type'    => isset($userId) ? 'user' : null,
            'source_context'    => $meta['source_context'] ?? null,
            'processing_status' => 'recorded',
            'occurred_at'       => now(),
        ]);
    }

    private function handleFailure(DomainEventInterface $event, \Throwable $e): void
    {
        Log::error('intelligent.domain_event.failed', [
            'event_name'     => $event->name(),
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id'   => $event->aggregateId(),
            'company_id'     => $event->metadata()['company_id'] ?? null,
            'trace_id'       => TraceContext::traceIdForEvent(),
            'exception'      => $e::class,
            'message'        => $e->getMessage(),
            'result'         => 'failed',
        ]);

        if (! config('intelligent.record_failures.enabled')) {
            return;
        }

        try {
            EventRecordFailure::create([
                'event_name'     => $event->name(),
                'aggregate_type' => $event->aggregateType(),
                'aggregate_id'   => $event->aggregateId(),
                'company_id'     => $event->metadata()['company_id'] ?? null,
                'trace_id'       => TraceContext::traceIdForEvent(),
                'error_message'  => $e->getMessage(),
                'payload_json'   => $event->payload(),
                'created_at'     => now(),
            ]);
        } catch (\Throwable $inner) {
            Log::error('intelligent.event_record_failure.persist_failed', [
                'message' => $inner->getMessage(),
            ]);
        }
    }
}
