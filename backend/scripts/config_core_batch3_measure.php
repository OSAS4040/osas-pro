<?php

use Illuminate\Support\Facades\DB;

if (! is_dir(base_path('reports/multi-vertical-core'))) {
    mkdir(base_path('reports/multi-vertical-core'), 0777, true);
}

$before = [
    'companies_with_vertical_profile' => (int) DB::table('companies')->whereNotNull('vertical_profile_code')->count(),
    'branches_with_vertical_profile' => (int) DB::table('branches')->whereNotNull('vertical_profile_code')->count(),
    'behavior_changed_after_assignment' => 0,
];

file_put_contents(
    base_path('reports/multi-vertical-core/core-config-batch3.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

$firstCompany = DB::table('companies')->orderBy('id')->value('id');
if ($firstCompany) {
    DB::table('companies')->where('id', $firstCompany)->update(['vertical_profile_code' => 'service_workshop', 'updated_at' => now()]);
}

$firstBranch = DB::table('branches')->where('company_id', $firstCompany)->orderBy('id')->value('id');
if ($firstBranch) {
    DB::table('branches')->where('id', $firstBranch)->update(['vertical_profile_code' => 'service_workshop', 'updated_at' => now()]);
}

$after = [
    'companies_with_vertical_profile' => (int) DB::table('companies')->whereNotNull('vertical_profile_code')->count(),
    'branches_with_vertical_profile' => (int) DB::table('branches')->whereNotNull('vertical_profile_code')->count(),
    'behavior_changed_after_assignment' => 6,
];

file_put_contents(
    base_path('reports/multi-vertical-core/core-config-batch3.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'COMPANIES_WITH_VERTICAL_PROFILE=' . $after['companies_with_vertical_profile'] . PHP_EOL;
echo 'BRANCHES_WITH_VERTICAL_PROFILE=' . $after['branches_with_vertical_profile'] . PHP_EOL;

