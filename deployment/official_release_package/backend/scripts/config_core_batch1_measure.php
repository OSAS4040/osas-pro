<?php

use Illuminate\Support\Facades\DB;

if (! is_dir(base_path('reports/multi-vertical-core'))) {
    mkdir(base_path('reports/multi-vertical-core'), 0777, true);
}

$before = [
    'vertical_profiles_total' => (int) DB::table('vertical_profiles')->count(),
    'config_settings_total' => (int) DB::table('config_settings')->count(),
    'companies_with_vertical_profile' => (int) DB::table('companies')->whereNotNull('vertical_profile_code')->count(),
    'branches_with_vertical_profile' => (int) DB::table('branches')->whereNotNull('vertical_profile_code')->count(),
];

file_put_contents(
    base_path('reports/multi-vertical-core/core-config-extraction.batch1.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

if ((int) DB::table('vertical_profiles')->count() === 0) {
    DB::table('vertical_profiles')->insert([
        [
            'code' => 'service_workshop',
            'name' => 'Service Workshop',
            'description' => 'Measurement seed profile',
            'defaults' => json_encode(['booking.enabled' => true]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'code' => 'fleet_operations',
            'name' => 'Fleet Operations',
            'description' => 'Measurement seed profile',
            'defaults' => json_encode(['fleet.portal.enabled' => true]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'code' => 'retail_pos',
            'name' => 'Retail POS',
            'description' => 'Measurement seed profile',
            'defaults' => json_encode(['pos.quick_sale.enabled' => true]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
}

$after = [
    'vertical_profiles_total' => (int) DB::table('vertical_profiles')->count(),
    'config_settings_total' => (int) DB::table('config_settings')->count(),
    'companies_with_vertical_profile' => (int) DB::table('companies')->whereNotNull('vertical_profile_code')->count(),
    'branches_with_vertical_profile' => (int) DB::table('branches')->whereNotNull('vertical_profile_code')->count(),
];

file_put_contents(
    base_path('reports/multi-vertical-core/core-config-extraction.batch1.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'MVC_VERTICAL_PROFILES='.$after['vertical_profiles_total'].PHP_EOL;
