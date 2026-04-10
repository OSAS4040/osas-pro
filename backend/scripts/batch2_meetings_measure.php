<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (! is_dir(base_path('reports/institutional-capabilities'))) {
    mkdir(base_path('reports/institutional-capabilities'), 0777, true);
}

$before = [
    'meetings_total' => (int) DB::table('meetings')->count(),
    'decisions_total' => (int) DB::table('meeting_decisions')->count(),
    'actions_total' => (int) DB::table('meeting_actions')->count(),
    'closed_meetings' => (int) DB::table('meetings')->where('status', 'closed')->count(),
    'linked_entities_count' => (int) DB::table('meetings')->whereNotNull('linked_entity_type')->count(),
];
file_put_contents(
    base_path('reports/institutional-capabilities/meetings-mvp.batch2.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$companyId = 1;
$userId = (int) (DB::table('users')->where('company_id', $companyId)->value('id') ?: DB::table('users')->value('id') ?: 1);
$meetingId = DB::table('meetings')->insertGetId([
    'uuid' => (string) Str::uuid(),
    'company_id' => $companyId,
    'branch_id' => null,
    'title' => 'Batch2 measurement meeting',
    'agenda' => 'measurement',
    'status' => 'closed',
    'scheduled_at' => now(),
    'started_at' => now(),
    'closed_at' => now(),
    'created_by_user_id' => $userId,
    'linked_entity_type' => 'governance_item',
    'linked_entity_id' => 901,
    'trace_id' => (string) Str::uuid(),
    'created_at' => now(),
    'updated_at' => now(),
]);
DB::table('meeting_decisions')->insert([
    'meeting_id' => $meetingId,
    'company_id' => $companyId,
    'decision_text' => 'measurement decision',
    'created_by_user_id' => $userId,
    'decided_at' => now(),
    'trace_id' => (string) Str::uuid(),
    'created_at' => now(),
    'updated_at' => now(),
]);
DB::table('meeting_actions')->insert([
    'meeting_id' => $meetingId,
    'company_id' => $companyId,
    'action_text' => 'measurement action',
    'owner_user_id' => $userId,
    'follow_up_status' => 'open',
    'due_date' => now()->addDay()->toDateString(),
    'created_by_user_id' => $userId,
    'trace_id' => (string) Str::uuid(),
    'created_at' => now(),
    'updated_at' => now(),
]);

$after = [
    'meetings_total' => (int) DB::table('meetings')->count(),
    'decisions_total' => (int) DB::table('meeting_decisions')->count(),
    'actions_total' => (int) DB::table('meeting_actions')->count(),
    'closed_meetings' => (int) DB::table('meetings')->where('status', 'closed')->count(),
    'linked_entities_count' => (int) DB::table('meetings')->whereNotNull('linked_entity_type')->count(),
];
file_put_contents(
    base_path('reports/institutional-capabilities/meetings-mvp.batch2.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'B2_MEETINGS='.$after['meetings_total'].PHP_EOL;
echo 'B2_DECISIONS='.$after['decisions_total'].PHP_EOL;
echo 'B2_ACTIONS='.$after['actions_total'].PHP_EOL;
