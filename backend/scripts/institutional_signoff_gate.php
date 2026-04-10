<?php

use Illuminate\Support\Facades\DB;

if (! is_dir(base_path('reports/institutional-capabilities'))) {
    mkdir(base_path('reports/institutional-capabilities'), 0777, true);
}

$data = [
    'generated_at' => now()->toIso8601String(),
    'meetings_total' => (int) DB::table('meetings')->count(),
    'decisions_total' => (int) DB::table('meeting_decisions')->count(),
    'actions_total' => (int) DB::table('meeting_actions')->count(),
    'closed_meetings' => (int) DB::table('meetings')->where('status', 'closed')->count(),
    'decisions_with_approval' => (int) DB::table('meeting_decisions')->where('requires_approval', true)->count(),
    'approval_workflows_linked_to_meeting_decisions' => (int) DB::table('meeting_decisions')->whereNotNull('approval_workflow_id')->count(),
    'open_actions' => (int) DB::table('meeting_actions')->where('status', 'open')->count(),
    'done_actions' => (int) DB::table('meeting_actions')->where('status', 'done')->count(),
    'linked_execution_entities_count' => (int) DB::table('meetings')
        ->whereIn('linked_entity_type', ['governance_item', 'work_order', 'support_ticket'])
        ->count(),
    'approval_workflows_total' => (int) DB::table('approval_workflows')->count(),
    'approval_actions_total' => (int) DB::table('approval_workflow_actions')->count(),
];

file_put_contents(
    base_path('reports/institutional-capabilities/institutional-phase-signoff-gate-final.json'),
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'INST_GATE_MEETINGS='.$data['meetings_total'].PHP_EOL;
