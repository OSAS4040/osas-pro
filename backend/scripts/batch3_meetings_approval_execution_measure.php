<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

if (! is_dir(base_path('reports/institutional-capabilities'))) {
    mkdir(base_path('reports/institutional-capabilities'), 0777, true);
}

$before = [
    'meetings_total' => (int) DB::table('meetings')->count(),
    'decisions_total' => (int) DB::table('meeting_decisions')->count(),
    'decisions_with_approval' => (int) DB::table('meeting_decisions')->where('requires_approval', true)->count(),
    'approval_workflows_linked_to_meeting_decisions' => (int) DB::table('meeting_decisions')->whereNotNull('approval_workflow_id')->count(),
    'actions_total' => (int) DB::table('meeting_actions')->count(),
    'open_actions' => (int) DB::table('meeting_actions')->where('status', 'open')->count(),
    'done_actions' => (int) DB::table('meeting_actions')->where('status', 'done')->count(),
    'linked_execution_entities_count' => (int) DB::table('meetings')
        ->whereIn('linked_entity_type', ['governance_item', 'work_order', 'support_ticket'])
        ->count(),
];
file_put_contents(
    base_path('reports/institutional-capabilities/meetings-approval-execution.batch3.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$companyId = 1;
$userId = (int) (DB::table('users')->where('company_id', $companyId)->value('id') ?: DB::table('users')->value('id') ?: 1);
$meetingId = DB::table('meetings')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'company_id' => $companyId,
    'branch_id' => null,
    'title' => 'Batch3 measurement bridge',
    'agenda' => 'bridge measure',
    'status' => 'in_progress',
    'scheduled_at' => now(),
    'started_at' => now(),
    'created_by_user_id' => $userId,
    'linked_entity_type' => 'support_ticket',
    'linked_entity_id' => 777,
    'trace_id' => (string) Str::uuid(),
    'created_at' => now(),
    'updated_at' => now(),
]);
$decisionId = DB::table('meeting_decisions')->insertGetId([
    'meeting_id' => $meetingId,
    'company_id' => $companyId,
    'decision_text' => 'measurement approval decision',
    'requires_approval' => true,
    'approval_status' => 'pending',
    'created_by_user_id' => $userId,
    'decided_at' => now(),
    'trace_id' => (string) Str::uuid(),
    'created_at' => now(),
    'updated_at' => now(),
]);
$workflowId = DB::table('approval_workflows')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'company_id' => $companyId,
    'subject_type' => 'meeting_decision',
    'subject_id' => $decisionId,
    'policy_code' => 'meeting.decision.approval',
    'status' => 'approved',
    'current_step' => 2,
    'total_steps' => 1,
    'requested_by' => $userId,
    'assigned_approver' => $userId,
    'resolved_by' => $userId,
    'resolved_at' => now(),
    'acted_at' => now(),
    'requester_note' => 'measure',
    'resolver_note' => 'measure approve',
    'trace_id' => (string) Str::uuid(),
    'meta' => json_encode(['meeting_id' => $meetingId]),
    'created_at' => now(),
    'updated_at' => now(),
]);
DB::table('meeting_decisions')->where('id', $decisionId)->update(['approval_workflow_id' => $workflowId, 'approval_status' => 'approved', 'updated_at' => now()]);
DB::table('meeting_actions')->insert([
    'meeting_id' => $meetingId,
    'decision_id' => $decisionId,
    'company_id' => $companyId,
    'action_text' => 'measurement execution action',
    'owner_user_id' => $userId,
    'owner_employee_id' => null,
    'status' => 'done',
    'follow_up_status' => 'done',
    'due_date' => now()->addDay()->toDateString(),
    'closed_at' => now(),
    'created_by_user_id' => $userId,
    'trace_id' => (string) Str::uuid(),
    'created_at' => now(),
    'updated_at' => now(),
]);

$after = [
    'meetings_total' => (int) DB::table('meetings')->count(),
    'decisions_total' => (int) DB::table('meeting_decisions')->count(),
    'decisions_with_approval' => (int) DB::table('meeting_decisions')->where('requires_approval', true)->count(),
    'approval_workflows_linked_to_meeting_decisions' => (int) DB::table('meeting_decisions')->whereNotNull('approval_workflow_id')->count(),
    'actions_total' => (int) DB::table('meeting_actions')->count(),
    'open_actions' => (int) DB::table('meeting_actions')->where('status', 'open')->count(),
    'done_actions' => (int) DB::table('meeting_actions')->where('status', 'done')->count(),
    'linked_execution_entities_count' => (int) DB::table('meetings')
        ->whereIn('linked_entity_type', ['governance_item', 'work_order', 'support_ticket'])
        ->count(),
];
file_put_contents(
    base_path('reports/institutional-capabilities/meetings-approval-execution.batch3.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'B3_DECISIONS_WITH_APPROVAL='.$after['decisions_with_approval'].PHP_EOL;
echo 'B3_ACTIONS_DONE='.$after['done_actions'].PHP_EOL;
