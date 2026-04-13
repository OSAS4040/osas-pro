<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ConfigSetting;
use Illuminate\Database\Seeder;

class ConfigSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'inventory.require_reservation' => true,
            'inventory.allow_negative_stock' => false,
            'inventory.track_expiry' => false,
            'work_orders.require_bay_assignment' => true,
            'work_orders.require_vehicle_plate' => false,
            'work_orders.allow_quick_order' => false,
            'bookings.enabled' => true,
            'quotes.enabled' => true,
            'wallet.enabled' => true,
            'fleet.approval_required' => true,
            'pos.quick_sale_enabled' => true,
            'pos.require_customer' => false,
            'pos.enable_cash_only_mode' => false,
            'services.require_estimated_minutes' => false,
        ];

        foreach ($defaults as $key => $value) {
            $this->upsertSetting('system', 'system', $key, $value);
        }

        // Baseline plan-level examples (kept minimal and overridable by deeper scopes).
        $this->upsertSetting('plan', 'starter', 'wallet.enabled', false);
        $this->upsertSetting('plan', 'starter', 'quotes.enabled', false);

        // Vertical profile defaults.
        $this->upsertSetting('vertical', 'retail_pos', 'quotes.enabled', true);
        $this->upsertSetting('vertical', 'retail_pos', 'wallet.enabled', true);
        $this->upsertSetting('vertical', 'retail_pos', 'pos.quick_sale_enabled', true);
        $this->upsertSetting('vertical', 'retail_pos', 'pos.require_customer', true);
        $this->upsertSetting('vertical', 'retail_pos', 'pos.enable_cash_only_mode', true);
        $this->upsertSetting('vertical', 'retail_pos', 'work_orders.allow_quick_order', true);
        $this->upsertSetting('vertical', 'service_workshop', 'work_orders.require_bay_assignment', true);
        $this->upsertSetting('vertical', 'service_workshop', 'work_orders.require_vehicle_plate', true);
        $this->upsertSetting('vertical', 'service_workshop', 'services.require_estimated_minutes', true);
        $this->upsertSetting('vertical', 'service_workshop', 'inventory.track_expiry', true);
        $this->upsertSetting('vertical', 'service_workshop', 'bookings.enabled', true);
        $this->upsertSetting('vertical', 'fleet_operations', 'fleet.approval_required', true);
        $this->upsertSetting('vertical', 'fleet_operations', 'inventory.allow_negative_stock', true);

        // Seed one real company + branch override sample if tenant records exist.
        $companyId = Company::query()->orderBy('id')->value('id');
        if ($companyId) {
            $this->upsertSetting('company', (string) $companyId, 'quotes.enabled', true);
            $this->upsertSetting('company', (string) $companyId, 'wallet.enabled', true);
        }

        $branchId = Branch::query()->where('company_id', $companyId)->orderBy('id')->value('id');
        if ($branchId) {
            $this->upsertSetting('branch', (string) $branchId, 'bookings.enabled', true);
        }
    }

    private function upsertSetting(string $scopeType, string $scopeKey, string $configKey, bool $value): void
    {
        ConfigSetting::query()->updateOrCreate(
            [
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
                'config_key' => $configKey,
            ],
            [
                'config_value' => $value ? 'true' : 'false',
                'value_type' => 'boolean',
                'is_active' => true,
                'created_by_user_id' => null,
            ]
        );
    }
}

