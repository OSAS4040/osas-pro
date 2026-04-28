<?php

namespace Tests\Feature\Meetings;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_02_PROGRESS_REPORT.md — اجتماعات / تشغيل تجاري
 */
#[Group('phase2')]
class MeetingsMvpBatch2Test extends TestCase
{
    public function test_create_meeting(): void
    {
        $tenant = $this->createTenant();
        $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/meetings', [
                'title' => 'Ops governance sync',
                'agenda' => 'Follow-up actions',
                'linked_entity_type' => 'governance_item',
                'linked_entity_id' => 11,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_invalid_transition_to_close_from_draft(): void
    {
        $tenant = $this->createTenant();
        $meetingId = (int) $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/meetings', ['title' => 'Draft meeting'])
            ->json('data.id');

        $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/close")
            ->assertStatus(409)
            ->assertJsonPath('code', 'TRANSITION_NOT_ALLOWED');
    }

    public function test_add_decision_and_action(): void
    {
        $tenant = $this->createTenant();
        $meetingId = (int) $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/meetings', ['title' => 'Action meeting'])
            ->json('data.id');

        $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/decisions", ['decision_text' => 'Approve scope'])
            ->assertStatus(201);
        $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/actions", ['action_text' => 'Execute batch'])
            ->assertStatus(201);
    }

    public function test_unauthorized_action(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createUser($tenant['company'], $tenant['branch'], 'cashier');
        $meetingId = (int) $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/meetings', ['title' => 'Restricted meeting'])
            ->json('data.id');

        $this->actingAsUser($cashier)
            ->postJson("/api/v1/meetings/{$meetingId}/close")
            ->assertStatus(403);
    }

    public function test_close_meeting_and_audit_trail(): void
    {
        $tenant = $this->createTenant();
        $meetingId = (int) $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/meetings', ['title' => 'Close flow meeting'])
            ->json('data.id');

        $this->actingAsUser($tenant['user'])
            ->putJson("/api/v1/meetings/{$meetingId}", ['status' => 'scheduled'])
            ->assertOk();
        $this->actingAsUser($tenant['user'])
            ->postJson("/api/v1/meetings/{$meetingId}/close")
            ->assertOk();

        $this->assertDatabaseHas('meetings', ['id' => $meetingId, 'status' => 'closed']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'meeting', 'subject_id' => $meetingId, 'action' => 'meeting.closed']);
    }
}
