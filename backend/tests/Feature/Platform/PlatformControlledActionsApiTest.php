<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\PlatformControlledAction;
use App\Models\PlatformIncident;
use App\Models\User;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionArtifactType;
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
final class PlatformControlledActionsApiTest extends TestCase
{
    private function seedIncident(string $key = 'ctrl_act_demo', string $status = 'open'): void
    {
        PlatformIncident::query()->create([
            'incident_key' => $key,
            'incident_type' => 'candidate.single_signal',
            'title' => 'عنوان',
            'summary' => 'ملخص',
            'why_summary' => 'لماذا',
            'severity' => 'low',
            'confidence' => 0.75,
            'status' => $status,
            'owner' => null,
            'ownership_state' => 'unassigned',
            'escalation_state' => 'none',
            'affected_scope' => 'tenant:1',
            'affected_entities' => [],
            'affected_companies' => [1],
            'source_signals' => ['sig_x'],
            'recommended_actions' => ['راقب'],
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

    public function test_list_requires_view_and_incidents_read(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-aud@platform.test', ['platform_role' => 'auditor']);
        $user = User::where('email', 'ctrl-aud@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_aud');

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/ctrl_inc_aud/controlled-actions')
            ->assertSuccessful();
    }

    public function test_finance_admin_cannot_list_controlled_actions(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-fin@platform.test', ['platform_role' => 'finance_admin']);
        $user = User::where('email', 'ctrl-fin@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_fin');

        $this->actingAsUser($user)
            ->getJson('/api/v1/platform/intelligence/incidents/ctrl_inc_fin/controlled-actions')
            ->assertForbidden();
    }

    public function test_create_follow_up_and_idempotency_and_no_incident_mutation(): void
    {
        Config::set('platform.admin_enabled', true);
        Config::set('saas.platform_admin_emails', ['ctrl-ops@platform.test']);
        $this->createStandalonePlatformOperator('ctrl-ops@platform.test', ['platform_role' => 'operations_admin']);
        $user = User::where('email', 'ctrl-ops@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_1');

        $trace = new InMemoryPlatformIntelligenceTraceRecorder;
        $this->app->instance(PlatformIntelligenceTraceRecorderInterface::class, $trace);

        $p1 = $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_1/controlled-actions/create-follow-up', [
            'action_summary' => 'متابعة تشغيلية',
            'idempotency_key' => 'idem-1',
        ]);
        $p1->assertCreated();
        $id = $p1->json('data.action_id');
        $this->assertNotEmpty($id);

        $p2 = $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_1/controlled-actions/create-follow-up', [
            'action_summary' => 'ignored on replay',
            'idempotency_key' => 'idem-1',
        ]);
        $p2->assertCreated();
        $this->assertSame($id, $p2->json('data.action_id'));

        $this->assertSame(1, PlatformControlledAction::query()->where('incident_key', 'ctrl_inc_1')->count());

        $inc = PlatformIncident::query()->where('incident_key', 'ctrl_inc_1')->firstOrFail();
        $this->assertSame('open', $inc->status);

        $types = array_map(static fn ($e) => $e->event_type, $trace->all());
        $this->assertContains(PlatformIntelligenceTraceEventType::ControlledActionCreated, $types);
    }

    public function test_human_review_duplicate_blocked(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-hr@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-hr@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_hr');

        $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_hr/controlled-actions/request-human-review', [
            'action_summary' => 'مراجعة بشرية',
        ])->assertCreated();

        $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_hr/controlled-actions/request-human-review', [
            'action_summary' => 'ثانية',
        ])->assertStatus(422);
    }

    public function test_assign_schedule_complete_cancel_reopen_flow(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-flow@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-flow@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_flow');

        $aid = $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_flow/controlled-actions/create-follow-up', [
            'action_summary' => 'فلو كامل',
        ])->assertCreated()->json('data.action_id');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/assign-owner", [
            'assigned_owner' => 'user:'.$user->id,
        ])->assertSuccessful()->assertJsonPath('data.status', 'assigned');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/schedule-follow-up-window", [
            'scheduled_for' => now()->addDay()->toIso8601String(),
        ])->assertSuccessful()->assertJsonPath('data.status', 'scheduled');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/mark-completed", [
            'completion_reason' => 'تم التحقق',
        ])->assertSuccessful()->assertJsonPath('data.status', 'completed');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/reopen", [])
            ->assertSuccessful()
            ->assertJsonPath('data.status', 'open');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/cancel", [
            'canceled_reason' => 'لم يعد مطلوباً',
        ])->assertSuccessful()->assertJsonPath('data.status', 'canceled');
    }

    public function test_cancel_requires_reason(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-can@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-can@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_can');

        $aid = $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_can/controlled-actions/create-follow-up', [
            'action_summary' => 'x',
        ])->assertCreated()->json('data.action_id');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/cancel", [])
            ->assertStatus(422);
    }

    public function test_create_follow_up_forbidden_on_closed_incident(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-closed@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-closed@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_closed', 'closed');

        $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_closed/controlled-actions/create-follow-up', [
            'action_summary' => 'لا يجوز',
        ])->assertStatus(422);
    }

    public function test_link_internal_task_reference(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-link@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-link@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_link');

        $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_link/controlled-actions/link-internal-task-reference', [
            'external_reference' => 'task:internal-99',
            'action_summary' => 'ربط',
        ])->assertCreated()->assertJsonPath('data.action_type', ControlledActionArtifactType::InternalTaskReference->value);
    }

    public function test_schedule_rejects_invalid_datetime(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-badsched@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-badsched@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_badsched');

        $aid = $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_badsched/controlled-actions/create-follow-up', [
            'action_summary' => 'جدولة',
        ])->assertCreated()->json('data.action_id');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/schedule-follow-up-window", [
            'scheduled_for' => 'not-a-valid-date',
        ])->assertStatus(422);
    }

    public function test_complete_rejected_for_task_reference_artifact(): void
    {
        Config::set('platform.admin_enabled', true);
        $this->createStandalonePlatformOperator('ctrl-tc@platform.test', ['platform_role' => 'platform_admin']);
        $user = User::where('email', 'ctrl-tc@platform.test')->firstOrFail();
        $this->seedIncident('ctrl_inc_tc');

        $aid = $this->actingAsUser($user)->postJson('/api/v1/platform/intelligence/incidents/ctrl_inc_tc/controlled-actions/link-internal-task-reference', [
            'external_reference' => 'ticket:ABC-1',
        ])->assertCreated()->json('data.action_id');

        $this->actingAsUser($user)->postJson("/api/v1/platform/intelligence/controlled-actions/{$aid}/mark-completed", [
            'completion_reason' => 'should fail',
        ])->assertStatus(422);
    }
}
