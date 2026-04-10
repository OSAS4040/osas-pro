<?php

namespace Tests\Feature\Meetings;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeetingsApprovalExecutionBatch3Test extends TestCase
{
    public function test_decision_requires_approval_and_links_workflow(): void
    {
        $tenant = $this->createTenant();
        $meetingId = (int) $this->actingAsUser($tenant['user'])->postJson('/api/v1/meetings', [
            'title' => 'Batch3 decision approval',
            'linked_entity_type' => 'governance_item',
            'linked_entity_id' => 501,
        ])->json('data.id');

        $decisionId = (int) $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/decisions", [
                'decision_text' => 'Require approval',
                'requires_approval' => true,
            ])->json('data.id');

        $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/decisions/{$decisionId}/approval/start")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'pending');
    }

    public function test_unauthorized_approval_action_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createUser($tenant['company'], $tenant['branch'], 'cashier');
        $meetingId = (int) $this->actingAsUser($tenant['user'])->postJson('/api/v1/meetings', ['title' => 'Unauthorized approval'])->json('data.id');
        $decisionId = (int) $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/decisions", ['decision_text' => 'Needs approval', 'requires_approval' => true])
            ->json('data.id');
        $this->actingAsUser($tenant['user'])->postJson("/api/v1/meetings/{$meetingId}/decisions/{$decisionId}/approval/start")->assertOk();

        $this->actingAsUser($cashier)
            ->postJson("/api/v1/meetings/{$meetingId}/decisions/{$decisionId}/approve")
            ->assertStatus(403);
    }

    public function test_approve_reject_reflects_in_decision_status_read(): void
    {
        $tenant = $this->createTenant();
        $meetingId = (int) $this->actingAsUser($tenant['user'])->postJson('/api/v1/meetings', ['title' => 'Approval status read'])->json('data.id');
        $decisionId = (int) $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/decisions", ['decision_text' => 'Decision', 'requires_approval' => true])
            ->json('data.id');
        $this->actingAsUser($tenant['user'])->postJson("/api/v1/meetings/{$meetingId}/decisions/{$decisionId}/approval/start")->assertOk();
        $this->actingAsUser($tenant['user'])->postJson("/api/v1/meetings/{$meetingId}/decisions/{$decisionId}/approve")->assertOk();
        $this->actingAsUser($tenant['user'])
            ->getJson("/api/v1/meetings/{$meetingId}/decisions/{$decisionId}/approval-status")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved');
    }

    public function test_create_assign_update_and_close_action_with_transition_guard(): void
    {
        $tenant = $this->createTenant();
        $meetingId = (int) $this->actingAsUser($tenant['user'])->postJson('/api/v1/meetings', [
            'title' => 'Action tracking',
            'linked_entity_type' => 'work_order',
            'linked_entity_id' => 601,
        ])->json('data.id');

        $actionCreate = $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/actions", [
                'action_text' => 'Execute work',
                'owner_user_id' => $tenant['user']->id,
            ])->assertStatus(201);

        $actionId = (int) DB::table('meeting_actions')->where('meeting_id', $meetingId)->value('id');
        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/meetings/{$meetingId}/actions/{$actionId}", ['status' => 'in_progress'])
            ->assertOk();
        $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/actions/{$actionId}/close")
            ->assertOk();
        $this->actingAsUser($tenant['user'])
            ->patchJson("/api/v1/meetings/{$meetingId}/actions/{$actionId}", ['status' => 'open'])
            ->assertStatus(409);

        $this->assertDatabaseHas('meeting_actions', ['id' => $actionId, 'status' => 'done']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'meeting', 'subject_id' => $meetingId, 'action' => 'meeting.action.closed']);
    }
}
