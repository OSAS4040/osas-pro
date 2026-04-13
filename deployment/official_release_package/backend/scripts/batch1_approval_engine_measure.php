<?php

use App\Services\ApprovalWorkflowService;
use Illuminate\Support\Facades\DB;

if (! is_dir(base_path('reports/institutional-capabilities'))) {
    mkdir(base_path('reports/institutional-capabilities'), 0777, true);
}

$before = [
    'approval_workflows_total' => (int) DB::table('approval_workflows')->count(),
    'approval_actions_total' => (int) DB::table('approval_workflow_actions')->count(),
    'pending' => (int) DB::table('approval_workflows')->where('status', 'pending')->count(),
    'approved' => (int) DB::table('approval_workflows')->where('status', 'approved')->count(),
    'rejected' => (int) DB::table('approval_workflows')->where('status', 'rejected')->count(),
    'cancelled' => (int) DB::table('approval_workflows')->where('status', 'cancelled')->count(),
    'subjects_covered' => [
        'governance' => (int) DB::table('approval_workflows')->where('subject_type', 'governance_item')->count(),
        'leave' => (int) DB::table('approval_workflows')->where('subject_type', 'leave')->count(),
        'salary' => (int) DB::table('approval_workflows')->where('subject_type', 'salary')->count(),
        'work_order' => (int) DB::table('approval_workflows')->where('subject_type', 'work_order')->count(),
    ],
];

file_put_contents(
    base_path('reports/institutional-capabilities/approval-engine.batch1.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$service = app(ApprovalWorkflowService::class);
$companyId = 1;
$requestedBy = (int) (DB::table('users')->where('company_id', $companyId)->value('id') ?: DB::table('users')->value('id') ?: 1);
foreach ([
    ['governance_item', 9001, 'approved'],
    ['leave', 9002, 'rejected'],
    ['salary', 9003, 'approved'],
    ['work_order', 9004, 'approved'],
] as [$subjectType, $subjectId, $target]) {
    $service->request($companyId, $subjectType, $subjectId, $requestedBy, "{$subjectType}.approval", 'measurement', ['batch' => 1], $requestedBy, 2);
    $service->transitionBySubject($companyId, $subjectType, $subjectId, $target, $requestedBy, 'measurement action');
}

$after = [
    'approval_workflows_total' => (int) DB::table('approval_workflows')->count(),
    'approval_actions_total' => (int) DB::table('approval_workflow_actions')->count(),
    'pending' => (int) DB::table('approval_workflows')->where('status', 'pending')->count(),
    'approved' => (int) DB::table('approval_workflows')->where('status', 'approved')->count(),
    'rejected' => (int) DB::table('approval_workflows')->where('status', 'rejected')->count(),
    'cancelled' => (int) DB::table('approval_workflows')->where('status', 'cancelled')->count(),
    'subjects_covered' => [
        'governance' => (int) DB::table('approval_workflows')->where('subject_type', 'governance_item')->count(),
        'leave' => (int) DB::table('approval_workflows')->where('subject_type', 'leave')->count(),
        'salary' => (int) DB::table('approval_workflows')->where('subject_type', 'salary')->count(),
        'work_order' => (int) DB::table('approval_workflows')->where('subject_type', 'work_order')->count(),
    ],
];

file_put_contents(
    base_path('reports/institutional-capabilities/approval-engine.batch1.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'B1_APPROVAL_WORKFLOWS='.$after['approval_workflows_total'].PHP_EOL;
echo 'B1_APPROVAL_ACTIONS='.$after['approval_actions_total'].PHP_EOL;
