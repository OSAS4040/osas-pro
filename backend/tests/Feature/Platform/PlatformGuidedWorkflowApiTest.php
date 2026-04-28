<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\PlatformGuidedWorkflowIdempotency;
use App\Models\PlatformIncident;
use App\Models\User;
use App\Support\PlatformIntelligence\Trace\InMemoryPlatformIntelligenceTraceRecorder;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceEventType;
use App\Support\PlatformIntelligence\Trace\PlatformIntelligenceTraceRecorderInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_06_PROGRESS_REPORT.md
 */
#[Group('phase6')]
final class PlatformGuidedWorkflowApiTest extends TestCase
{
    public function test_workflows_catalog_requires_incidents_read(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-no@platform.test', [
            'platform_role' => 'unknown_role_no_permissions',
        ]);
        $user = User::where('email', 'gw-no@platform.test')->firstOrFail();

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/icand_gw/workflows')
            ->assertForbidden();
    }

    public function test_execute_requires_guided_workflows_permission(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-fin@platform.test', [
            'platform_role' => 'finance_admin',
        ]);
        $user = User::where('email', 'gw-fin@platform.test')->firstOrFail();
        $this->seedIncident('icand_gw_fin', 'open');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_fin/workflows/execute', $this->executeBody('acknowledge_assign', [
                'owner_ref' => 'user:'.$user->id,
            ]))
            ->assertForbidden();
    }

    public function test_acknowledge_assign_workflow_and_idempotency(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-aa@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-aa@platform.test')->firstOrFail();
        $this->seedIncident('icand_gw_aa', 'open');

        $trace = new InMemoryPlatformIntelligenceTraceRecorder;
        $this->app->instance(PlatformIntelligenceTraceRecorderInterface::class, $trace);

        $idem = (string) Str::uuid();
        $body = $this->executeBody('acknowledge_assign', [
            'owner_ref' => 'user:'.$user->id,
            'idempotency_key' => $idem,
        ]);

        $res = $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_aa/workflows/execute', $body);
        $res->assertOk()
            ->assertJsonPath('data.workflow_key', 'acknowledge_assign')
            ->assertJsonPath('data.final_status', 'acknowledged');

        $types = array_map(static fn ($e) => $e->event_type, $trace->all());
        $this->assertContains(PlatformIntelligenceTraceEventType::WorkflowStarted, $types);
        $this->assertContains(PlatformIntelligenceTraceEventType::WorkflowCompleted, $types);

        $res2 = $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_aa/workflows/execute', $body);
        $res2->assertOk();
        $this->assertSame($res->json(), $res2->json());

        $this->assertSame(1, PlatformGuidedWorkflowIdempotency::query()->where('idempotency_key', $idem)->count());
    }

    public function test_catalog_marks_unavailable_for_wrong_status(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-cat@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-cat@platform.test')->firstOrFail();
        $this->seedIncident('icand_gw_cat', 'closed');

        $res = $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/icand_gw_cat/workflows');
        $res->assertOk();
        $rows = $res->json('data');
        $this->assertIsArray($rows);
        $ack = collect($rows)->firstWhere('workflow_key', 'acknowledge_assign');
        $this->assertNotNull($ack);
        $this->assertFalse($ack['available']);
    }

    public function test_under_review_decision_and_escalate_decision_paths(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-path@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-path@platform.test')->firstOrFail();

        $this->seedIncident('icand_gw_path', 'acknowledged');
        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_path/workflows/execute', $this->executeBody('under_review_decision', [
                'decision_summary' => 'Summary for under review',
                'rationale' => 'Rationale text long enough',
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.final_status', 'under_review');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_path/workflows/execute', $this->executeBody('escalate_decision', [
                'decision_summary' => 'Escalation summary here',
                'rationale' => 'Escalation rationale text ok',
                'escalate_reason' => 'ops needs leadership',
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.final_status', 'escalated');
    }

    public function test_monitor_transition_then_resolve_closure(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-mon@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-mon@platform.test')->firstOrFail();

        $this->seedIncident('icand_gw_mon', 'under_review');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_mon/workflows/execute', $this->executeBody('monitor_transition', [
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.final_status', 'monitoring');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_mon/workflows/execute', $this->executeBody('resolve_closure', [
                'decision_summary' => 'Closure summary ok',
                'rationale' => 'Resolve and close rationale ok',
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.final_status', 'resolved');
    }

    public function test_close_final_requires_resolved_and_close_reason(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-close@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-close@platform.test')->firstOrFail();
        $this->seedIncident('icand_gw_close', 'resolved');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_close/workflows/execute', $this->executeBody('close_final', [
                'decision_summary' => 'Final institutional closure',
                'rationale' => 'Final rationale text ok',
                'close_reason' => 'Verified closure after resolution',
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.final_status', 'closed');
    }

    public function test_false_positive_workflow(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-fp@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-fp@platform.test')->firstOrFail();
        $this->seedIncident('icand_gw_fp', 'escalated');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_fp/workflows/execute', $this->executeBody('false_positive', [
                'decision_summary' => 'False positive classification',
                'rationale' => 'Reproduced twice with no signal recurrence',
                'idempotency_key' => (string) Str::uuid(),
            ]))
            ->assertOk()
            ->assertJsonPath('data.final_status', 'resolved');
    }

    public function test_execute_without_confirm_rejected(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('gw-cnf@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $user = User::where('email', 'gw-cnf@platform.test')->firstOrFail();
        $this->seedIncident('icand_gw_cnf', 'open');

        $this->actingAsUser($user)
            ->postJson('/api/v1/platform/intelligence/incidents/icand_gw_cnf/workflows/execute', [
                'workflow_key' => 'acknowledge_assign',
                'idempotency_key' => (string) Str::uuid(),
                'confirm' => false,
                'owner_ref' => 'user:'.$user->id,
            ])
            ->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function executeBody(string $workflowKey, array $overrides = []): array
    {
        return array_merge([
            'workflow_key' => $workflowKey,
            'confirm' => true,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);
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
            'acknowledged_at' => in_array($status, ['acknowledged', 'under_review', 'escalated', 'monitoring', 'resolved', 'closed'], true) ? now() : null,
            'resolved_at' => in_array($status, ['resolved', 'closed'], true) ? now() : null,
            'closed_at' => $status === 'closed' ? now() : null,
            'last_status_change_at' => now(),
            'resolve_reason' => in_array($status, ['resolved', 'closed'], true) ? 'seed' : null,
            'close_reason' => $status === 'closed' ? 'seed' : null,
            'operator_notes' => null,
        ]);
    }
}
