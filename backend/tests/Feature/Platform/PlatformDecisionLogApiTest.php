<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\PlatformDecisionLogEntry;
use App\Models\PlatformIncident;
use App\Models\PlatformIncidentLifecycleEvent;
use App\Models\User;
use App\Support\PlatformIntelligence\Trace\InMemoryPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformDecisionLogApiTest extends TestCase
{
    public function test_list_requires_decisions_read_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('dl-no-read@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);
        $user = User::where('email', 'dl-no-read@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/decisions?incident_key=icand_x')
            ->assertForbidden();
    }

    public function test_list_returns_404_when_incident_missing(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('dl-404@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'dl-404@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/decisions?incident_key=missing_key_xyz')
            ->assertNotFound();
    }

    public function test_list_orders_oldest_first_and_supports_filter(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('dl-list@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'dl-list@platform.test')->firstOrFail();

        $this->seedIncident('icand_dl_order', 'open');

        PlatformDecisionLogEntry::query()->create([
            'decision_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'incident_key' => 'icand_dl_order',
            'decision_type' => 'observation',
            'decision_summary' => 'First',
            'rationale' => 'R1',
            'actor_user_id' => $user->id,
            'linked_signals' => [],
            'linked_notes' => [],
            'expected_outcome' => '',
            'evidence_refs' => [],
            'follow_up_required' => false,
        ]);
        PlatformDecisionLogEntry::query()->create([
            'decision_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            'incident_key' => 'icand_dl_order',
            'decision_type' => 'monitor',
            'decision_summary' => 'Second',
            'rationale' => 'R2',
            'actor_user_id' => $user->id,
            'linked_signals' => ['sig_a'],
            'linked_notes' => [],
            'expected_outcome' => 'watch',
            'evidence_refs' => [],
            'follow_up_required' => true,
        ]);

        $res = $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/icand_dl_order/decisions?per_page=10');
        $res->assertOk();
        $ids = array_map(static fn (array $row) => $row['decision_id'], $res->json('data'));
        $this->assertSame([
            'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ], $ids);

        $filtered = $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/decisions?incident_key=icand_dl_order&decision_type=monitor');
        $filtered->assertOk();
        $this->assertCount(1, $filtered->json('data'));
        $this->assertSame('monitor', $filtered->json('data.0.decision_type'));
    }

    public function test_post_requires_write_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('dl-readonly@platform.test', [
            'platform_role' => 'finance_admin',
        ]);
        $user = User::where('email', 'dl-readonly@platform.test')->firstOrFail();

        $this->seedIncident('icand_dl_ro', 'open');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_dl_ro/decisions', [
                'decision_type' => 'observation',
                'decision_summary' => 'Summary text',
                'rationale' => 'Rationale text here',
            ])
            ->assertForbidden();
    }

    public function test_post_validates_required_fields_and_rejects_invalid_type(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('dl-val@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'dl-val@platform.test')->firstOrFail();
        $this->seedIncident('icand_dl_val', 'under_review');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_dl_val/decisions', [
                'decision_type' => 'not_a_real_type',
                'decision_summary' => 'x',
                'rationale' => 'y',
            ])
            ->assertStatus(422);

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_dl_val/decisions', [
                'decision_type' => 'observation',
                'decision_summary' => 'ab',
                'rationale' => 'short',
            ])
            ->assertStatus(422);
    }

    public function test_post_creates_entry_emits_trace_and_does_not_mutate_incident_lifecycle(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('dl-write@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'dl-write@platform.test')->firstOrFail();
        $this->seedIncident('icand_dl_write', 'under_review');

        $trace = new InMemoryPlatformIntelligenceTraceRecorder;
        $this->app->instance(PlatformIntelligenceTraceRecorderInterface::class, $trace);

        $lifecycleBefore = PlatformIncidentLifecycleEvent::query()->where('incident_key', 'icand_dl_write')->count();

        $res = $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_dl_write/decisions', [
                'decision_type' => 'observation',
                'decision_summary' => 'Operational observation recorded',
                'rationale' => 'Clear rationale for auditors and follow-up teams.',
                'expected_outcome' => 'Continue monitoring',
                'linked_signals' => ['sig_1'],
                'linked_notes' => ['note:ref:1'],
                'evidence_refs' => ['artifact:runbook/section-3'],
                'follow_up_required' => true,
            ]);

        $res->assertCreated()
            ->assertJsonPath('data.incident_key', 'icand_dl_write')
            ->assertJsonPath('data.decision_type', 'observation')
            ->assertJsonPath('data.follow_up_required', true);

        $incident = PlatformIncident::query()->where('incident_key', 'icand_dl_write')->firstOrFail();
        $this->assertSame('under_review', $incident->status);

        $lifecycleAfter = PlatformIncidentLifecycleEvent::query()->where('incident_key', 'icand_dl_write')->count();
        $this->assertSame($lifecycleBefore, $lifecycleAfter);

        $events = $trace->all();
        $this->assertNotEmpty($events);
        $last = $events[array_key_last($events)];
        $this->assertSame(PlatformIntelligenceTraceEventType::DecisionRecorded, $last->event_type);
        $this->assertSame('icand_dl_write', $last->linked_entity_key);
        $this->assertSame('observation', $last->context['decision_type'] ?? null);
    }

    /**
     * @param  non-empty-string  $key
     */
    private function seedIncident(string $key, string $status): void
    {
        PlatformIncident::query()->create([
            'incident_key' => $key,
            'incident_type' => 'candidate.single_signal',
            'title' => 'T',
            'summary' => 'S',
            'why_summary' => 'W',
            'severity' => 'low',
            'confidence' => 0.5,
            'status' => $status,
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
    }
}
