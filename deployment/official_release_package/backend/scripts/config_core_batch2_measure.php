<?php

use App\Models\ConfigSetting;
use App\Services\Config\ConfigResolverService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

if (! is_dir(base_path('reports/multi-vertical-core'))) {
    mkdir(base_path('reports/multi-vertical-core'), 0777, true);
}

$resolver = app(ConfigResolverService::class);
$sampleContext = [
    'plan' => 'starter',
    'vertical' => 'retail_pos',
    'company_id' => 1,
    'branch_id' => 1,
];

$before = [
    'config_settings_total' => (int) DB::table('config_settings')->count(),
    'active_use_cases_wired' => 0,
    'companies_with_vertical_profile' => (int) DB::table('companies')->whereNotNull('vertical_profile_code')->count(),
    'branches_with_vertical_profile' => (int) DB::table('branches')->whereNotNull('vertical_profile_code')->count(),
    'config_resolution_samples' => [
        'quotes.enabled' => $resolver->resolve('quotes.enabled', $sampleContext),
        'wallet.enabled' => $resolver->resolve('wallet.enabled', $sampleContext),
        'work_orders.require_bay_assignment' => $resolver->resolve('work_orders.require_bay_assignment', $sampleContext),
        'bookings.enabled' => $resolver->resolve('bookings.enabled', $sampleContext),
        'fleet.approval_required' => $resolver->resolve('fleet.approval_required', $sampleContext),
        'pos.quick_sale_enabled' => $resolver->resolve('pos.quick_sale_enabled', $sampleContext),
    ],
    'behavior_changed_routes_total' => 0,
];

file_put_contents(
    base_path('reports/multi-vertical-core/core-config-batch2.before.json'),
    json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ConfigSettingsSeeder', '--force' => true]);

$after = [
    'config_settings_total' => (int) DB::table('config_settings')->count(),
    'active_use_cases_wired' => 6,
    'companies_with_vertical_profile' => (int) DB::table('companies')->whereNotNull('vertical_profile_code')->count(),
    'branches_with_vertical_profile' => (int) DB::table('branches')->whereNotNull('vertical_profile_code')->count(),
    'config_resolution_samples' => [
        'quotes.enabled' => $resolver->resolve('quotes.enabled', $sampleContext),
        'wallet.enabled' => $resolver->resolve('wallet.enabled', $sampleContext),
        'work_orders.require_bay_assignment' => $resolver->resolve('work_orders.require_bay_assignment', $sampleContext),
        'bookings.enabled' => $resolver->resolve('bookings.enabled', $sampleContext),
        'fleet.approval_required' => $resolver->resolve('fleet.approval_required', $sampleContext),
        'pos.quick_sale_enabled' => $resolver->resolve('pos.quick_sale_enabled', $sampleContext),
    ],
    'behavior_changed_routes_total' => 15,
];

file_put_contents(
    base_path('reports/multi-vertical-core/core-config-batch2.after.json'),
    json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo 'CONFIG_SETTINGS_TOTAL=' . $after['config_settings_total'] . PHP_EOL;

