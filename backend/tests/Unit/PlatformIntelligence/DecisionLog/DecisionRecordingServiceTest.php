<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\DecisionLog;

use App\Models\PlatformIncident;
use App\Models\PlatformIncidentLifecycleEvent;
use App\Support\PlatformIntelligence\DecisionLog\DecisionRecordingException;
use App\Support\PlatformIntelligence\DecisionLog\DecisionRecordingService;
use App\Support\PlatformIntelligence\DecisionLog\DecisionTraceEmitter;
use App\Support\PlatformIntelligence\IncidentCenter\IncidentRepository;
use App\Support\PlatformIntelligence\Trace\InMemoryPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use Tests\TestCase;

final class DecisionRecordingServiceTest extends TestCase
{
    public function test_record_throws_when_incident_missing(): void
    {
        $user = $this->createStandalonePlatformOperator('dl-svc-miss@platform.test', [
            'platform_role' => 'platform_admin',
        ]);

        $trace = new InMemoryPlatformIntelligenceTraceRecorder;
        $this->app->instance(PlatformIntelligenceTraceRecorderInterface::class, $trace);

        $svc = $this->app->make(DecisionRecordingService::class);

        $this->expectException(DecisionRecordingException::class);
        $this->expectExceptionMessage('incident_not_found');

        $svc->record('no_such_incident', $user, [
            'decision_type' => 'observation',
            'decision_summary' => 'Summary text ok',
            'rationale' => 'Rationale text ok',
        ]);
    }

    public function test_record_persists_contract_shape_and_skips_lifecycle_table(): void
    {
        $user = $this->createStandalonePlatformOperator('dl-svc-ok@platform.test', [
            'platform_role' => 'platform_admin',
        ]);

        PlatformIncident::query()->create([
            'incident_key' => 'icand_dl_svc',
            'incident_type' => 'candidate.single_signal',
            'title' => 'T',
            'summary' => 'S',
            'why_summary' => 'W',
            'severity' => 'low',
            'confidence' => 0.5,
            'status' => 'open',
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => [],
            'recommended_actions' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'acknowledged_at' => null,
            'resolved_at' => null,
            'closed_at' => null,
            'last_status_change_at' => now(),
            'resolve_reason' => null,
            'close_reason' => null,
            'operator_notes' => null,
        ]);

        $trace = new InMemoryPlatformIntelligenceTraceRecorder;
        $this->app->instance(PlatformIntelligenceTraceRecorderInterface::class, $trace);

        $svc = new DecisionRecordingService(
            $this->app->make(IncidentRepository::class),
            $this->app->make(DecisionTraceEmitter::class),
        );

        $beforeLifecycle = PlatformIncidentLifecycleEvent::query()->count();

        $contract = $svc->record('icand_dl_svc', $user, [
            'decision_type' => 'false_positive',
            'decision_summary' => 'Classified as false positive after review',
            'rationale' => 'Signal cluster did not reproduce on two independent checks.',
            'expected_outcome' => 'No further action',
            'follow_up_required' => false,
        ]);

        $this->assertSame('icand_dl_svc', $contract->incident_key);
        $this->assertSame('false_positive', $contract->decision_type->value);
        $this->assertSame('user:'.$user->id, $contract->actor);
        $this->assertNotSame('', $contract->decision_id);

        $this->assertSame($beforeLifecycle, PlatformIncidentLifecycleEvent::query()->count());

        $this->assertCount(1, $trace->all());
        $this->assertSame(PlatformIntelligenceTraceEventType::DecisionRecorded, $trace->all()[0]->event_type);
    }
}
