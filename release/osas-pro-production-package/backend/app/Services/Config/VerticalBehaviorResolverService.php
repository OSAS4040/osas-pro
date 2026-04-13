<?php

namespace App\Services\Config;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;

class VerticalBehaviorResolverService
{
    public function __construct(private readonly ConfigResolverService $configResolver) {}

    public function resolve(int $companyId, ?int $branchId): array
    {
        $company = Company::query()->findOrFail($companyId);
        $branch = $branchId ? Branch::query()->where('company_id', $companyId)->find($branchId) : null;
        $plan = Subscription::query()->where('company_id', $companyId)->latest('id')->value('plan');
        $vertical = $branch?->vertical_profile_code ?: $company->vertical_profile_code;

        $context = [
            'plan' => $plan,
            'vertical' => $vertical,
            'company_id' => $companyId,
            'branch_id' => $branchId,
        ];

        return [
            'features' => [
                'work_orders.quick_order' => $this->configResolver->resolveBool('work_orders.allow_quick_order', $context, false),
                'inventory.expiry_tracking' => $this->configResolver->resolveBool('inventory.track_expiry', $context, false),
                'services.strict_catalog' => $this->configResolver->resolveBool('services.require_estimated_minutes', $context, false),
                'pos.quick_sale' => $this->configResolver->resolveBool('pos.quick_sale_enabled', $context, true),
            ],
            'rules' => [
                'work_orders.require_bay_assignment' => $this->configResolver->resolveBool('work_orders.require_bay_assignment', $context, false),
                'work_orders.require_vehicle_plate' => $this->configResolver->resolveBool('work_orders.require_vehicle_plate', $context, false),
                'inventory.allow_negative_stock' => $this->configResolver->resolveBool('inventory.allow_negative_stock', $context, false),
                'services.require_estimated_minutes' => $this->configResolver->resolveBool('services.require_estimated_minutes', $context, false),
                'pos.require_customer' => $this->configResolver->resolveBool('pos.require_customer', $context, false),
                'pos.enable_cash_only_mode' => $this->configResolver->resolveBool('pos.enable_cash_only_mode', $context, false),
            ],
            'flags' => [
                'require_vehicle_plate' => $this->configResolver->resolveBool('work_orders.require_vehicle_plate', $context, false),
                'allow_quick_order' => $this->configResolver->resolveBool('work_orders.allow_quick_order', $context, false),
                'track_expiry' => $this->configResolver->resolveBool('inventory.track_expiry', $context, false),
                'allow_negative_stock' => $this->configResolver->resolveBool('inventory.allow_negative_stock', $context, false),
                'require_customer' => $this->configResolver->resolveBool('pos.require_customer', $context, false),
                'enable_cash_only_mode' => $this->configResolver->resolveBool('pos.enable_cash_only_mode', $context, false),
            ],
        ];
    }

    /**
     * Active vertical/config markers for observability (e.g. pilot gate, runtime behavior traces).
     *
     * @param  array{rules?: array<string, bool>, flags?: array<string, bool>}  $resolved
     * @return list<string>
     */
    public function activeBehaviorMarkers(array $resolved): array
    {
        $markers = [];
        foreach ($resolved['features'] ?? [] as $name => $on) {
            if ($on) {
                $markers[] = 'feature.'.(string) $name;
            }
        }
        foreach ($resolved['flags'] ?? [] as $name => $on) {
            if ($on) {
                $markers[] = $name;
            }
        }
        foreach ($resolved['rules'] ?? [] as $name => $on) {
            if (! $on) {
                continue;
            }
            if (str_starts_with($name, 'work_orders.')) {
                $markers[] = substr($name, strlen('work_orders.'));
            } elseif (str_starts_with($name, 'pos.')) {
                $markers[] = substr($name, strlen('pos.'));
            } elseif (str_starts_with($name, 'inventory.')) {
                $markers[] = substr($name, strlen('inventory.'));
            } elseif (str_starts_with($name, 'services.')) {
                $markers[] = substr($name, strlen('services.'));
            }
        }

        return array_values(array_unique($markers));
    }
}

