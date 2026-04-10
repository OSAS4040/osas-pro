<?php

namespace Tests\Feature\Approvals;

use App\Services\ApprovalWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedApprovalEngineBatch1Test extends TestCase
{
    public function test_records_audit_trail_for_approve_reject_across_supported_subjects(): void
    {
        $tenant = $this->createTenant();
        $service = app(ApprovalWorkflowService::class);

        $subjects = [
            ['type' => 'governance_item', 'id' => 1001, 'target' => 'approved'],
            ['type' => 'leave', 'id' => 1002, 'target' => 'rejected'],
            ['type' => 'salary', 'id' => 1003, 'target' => 'approved'],
            ['type' => 'work_order', 'id' => 1004, 'target' => 'approved'],
        ];

        foreach ($subjects as $subject) {
            $service->request(
                (int) $tenant['company']->id,
                $subject['type'],
                $subject['id'],
                (int) $tenant['user']->id,
                policyCode: $subject['type'].'.approval',
                note: 'batch1 request',
                meta: ['batch' => 1],
                assignedApprover: (int) $tenant['user']->id,
                totalSteps: 2
            );

            $service->transitionBySubject(
                (int) $tenant['company']->id,
                $subject['type'],
                $subject['id'],
                $subject['target'],
                (int) $tenant['user']->id,
                'batch1 action note'
            );
        }

        $this->assertGreaterThanOrEqual(4, DB::table('approval_workflow_actions')->count());
        $this->assertDatabaseHas('approval_workflow_actions', [
            'new_status' => 'approved',
            'approval_step' => 2,
            'approval_note' => 'batch1 action note',
        ]);
        $this->assertDatabaseHas('approval_workflow_actions', [
            'new_status' => 'rejected',
            'approval_step' => 2,
        ]);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $service = app(ApprovalWorkflowService::class);

        $service->request((int) $tenant['company']->id, 'governance_item', 2001, (int) $tenant['user']->id);
        $service->transitionBySubject((int) $tenant['company']->id, 'governance_item', 2001, 'approved', (int) $tenant['user']->id);

        $this->expectException(\DomainException::class);
        $service->transitionBySubject((int) $tenant['company']->id, 'governance_item', 2001, 'rejected', (int) $tenant['user']->id);
    }

    public function test_unauthorized_action_on_governance_route_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createUser($tenant['company'], $tenant['branch'], 'cashier');

        $workflowId = DB::table('approval_workflows')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'subject_type' => 'governance_item',
            'subject_id' => 3001,
            'policy_code' => 'governance.workflow',
            'status' => 'pending',
            'current_step' => 1,
            'total_steps' => 1,
            'requested_by' => $tenant['user']->id,
            'assigned_approver' => $tenant['user']->id,
            'requester_note' => null,
            'resolver_note' => null,
            'trace_id' => (string) Str::uuid(),
            'meta' => json_encode(['batch' => 1]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsUser($cashier)
            ->postJson("/api/v1/governance/workflows/{$workflowId}/approve", ['note' => 'no auth'])
            ->assertStatus(403);
    }
}
