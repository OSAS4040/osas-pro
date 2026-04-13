<?php

namespace App\Services\Config;

use App\Models\ConfigSetting;
use App\Services\AuditLogger;

class ResolvedConfigVisibilityService
{
    public const CONFIG_KEYS = [
        'inventory.require_reservation',
        'inventory.allow_negative_stock',
        'work_orders.require_bay_assignment',
        'bookings.enabled',
        'quotes.enabled',
        'wallet.enabled',
        'fleet.approval_required',
        'pos.quick_sale_enabled',
    ];

    public function __construct(
        private readonly ConfigResolverService $resolver,
        private readonly AuditLogger $auditLogger
    ) {}

    public function resolveForCompany(int $companyId, ?string $plan, ?string $vertical, int $actorUserId): array
    {
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $value = $this->resolver->resolve($key, [
                'plan' => $plan,
                'vertical' => $vertical,
                'company_id' => $companyId,
                'branch_id' => null,
            ]);
            $config[$key] = [
                'value' => $value,
                'source' => $this->sourceForKey($key, $companyId, null),
            ];
        }

        $this->auditLogger->log(
            action: 'vertical_profile.resolution_check.company',
            subjectType: 'config_resolution',
            subjectId: $companyId,
            before: [],
            after: ['keys' => array_keys($config)],
            companyId: $companyId,
            branchId: null,
            userId: $actorUserId
        );

        return $config;
    }

    public function resolveForBranch(int $companyId, int $branchId, ?string $plan, ?string $vertical, int $actorUserId): array
    {
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $value = $this->resolver->resolve($key, [
                'plan' => $plan,
                'vertical' => $vertical,
                'company_id' => $companyId,
                'branch_id' => $branchId,
            ]);
            $config[$key] = [
                'value' => $value,
                'source' => $this->sourceForKey($key, $companyId, $branchId),
            ];
        }

        $this->auditLogger->log(
            action: 'vertical_profile.resolution_check.branch',
            subjectType: 'config_resolution',
            subjectId: $branchId,
            before: [],
            after: ['keys' => array_keys($config)],
            companyId: $companyId,
            branchId: $branchId,
            userId: $actorUserId
        );

        return $config;
    }

    private function sourceForKey(string $key, int $companyId, ?int $branchId): string
    {
        if ($branchId !== null && $this->hasSetting('branch', (string) $branchId, $key)) {
            return 'branch_override';
        }
        if ($this->hasSetting('company', (string) $companyId, $key)) {
            return 'company_override';
        }

        return 'default';
    }

    private function hasSetting(string $scopeType, string $scopeKey, string $key): bool
    {
        return ConfigSetting::query()
            ->where('scope_type', $scopeType)
            ->where('scope_key', $scopeKey)
            ->where('config_key', $key)
            ->where('is_active', true)
            ->exists();
    }
}

