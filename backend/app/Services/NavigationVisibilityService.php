<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ConfigSetting;
use App\Models\User;

class NavigationVisibilityService
{
    /** @var list<string> */
    private const SECTION_KEYS = [
        'operations',
        'hr',
        'finance_accounting',
        'inventory',
        'analytics',
        'admin',
        'platform',
        'subscription',
    ];

    /** @var list<string> */
    private const GROUP_KEYS = [
        'purchases',
        'accountant',
        'platform-center',
    ];

    /**
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    public function defaultPolicy(): array
    {
        return [
            'sections' => $this->allTrue(self::SECTION_KEYS),
            'groups' => $this->allTrue(self::GROUP_KEYS),
        ];
    }

    /**
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    public function platformPolicy(): array
    {
        $row = ConfigSetting::query()
            ->where('scope_type', 'system')
            ->where('scope_key', 'system')
            ->where('config_key', 'ui.navigation_visibility')
            ->where('is_active', true)
            ->first();

        return $this->normalizePolicy($row?->config_value);
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function updatePlatformPolicy(array $policy, ?int $actorUserId = null): array
    {
        $normalized = $this->normalizePolicy($policy);
        ConfigSetting::query()->updateOrCreate(
            [
                'scope_type' => 'system',
                'scope_key' => 'system',
                'config_key' => 'ui.navigation_visibility',
            ],
            [
                'config_value' => $normalized,
                'value_type' => 'json',
                'is_active' => true,
                'created_by_user_id' => $actorUserId,
            ]
        );

        return $normalized;
    }

    /**
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    public function companyPolicy(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];

        return $this->normalizePolicy($settings['navigation_visibility'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function updateCompanyPolicy(Company $company, array $policy): array
    {
        $platform = $this->platformPolicy();
        $normalized = $this->normalizePolicy($policy);
        $bounded = $this->intersectPolicy($platform, $normalized);

        $settings = is_array($company->settings) ? $company->settings : [];
        $settings['navigation_visibility'] = $bounded;
        $company->update(['settings' => $settings]);

        return $bounded;
    }

    /**
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    public function userOverride(User $user): array
    {
        $row = ConfigSetting::query()
            ->where('scope_type', 'user')
            ->where('scope_key', (string) $user->id)
            ->where('config_key', 'ui.navigation_visibility')
            ->where('is_active', true)
            ->first();

        return $this->normalizePolicy($row?->config_value);
    }

    /**
     * @param  array<string, mixed>  $policy
     */
    public function updateUserOverride(User $user, array $policy, ?int $actorUserId = null): array
    {
        $company = $user->company;
        $ceiling = $company instanceof Company
            ? $this->intersectPolicy($this->platformPolicy(), $this->companyPolicy($company))
            : $this->platformPolicy();

        $normalized = $this->normalizePolicy($policy);
        $bounded = $this->intersectPolicy($ceiling, $normalized);

        ConfigSetting::query()->updateOrCreate(
            [
                'scope_type' => 'user',
                'scope_key' => (string) $user->id,
                'config_key' => 'ui.navigation_visibility',
            ],
            [
                'config_value' => $bounded,
                'value_type' => 'json',
                'is_active' => true,
                'created_by_user_id' => $actorUserId,
            ]
        );

        return $bounded;
    }

    /**
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    public function effectiveForUser(User $user): array
    {
        $base = $this->defaultPolicy();
        $platform = $this->platformPolicy();
        $company = $user->company instanceof Company ? $this->companyPolicy($user->company) : $base;
        $userPolicy = $this->userOverride($user);

        return $this->intersectPolicy(
            $this->intersectPolicy($base, $platform),
            $this->intersectPolicy($company, $userPolicy),
        );
    }

    /**
     * @param  mixed  $raw
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    private function normalizePolicy(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        $defaults = $this->defaultPolicy();
        $sections = is_array($decoded['sections'] ?? null) ? $decoded['sections'] : [];
        $groups = is_array($decoded['groups'] ?? null) ? $decoded['groups'] : [];

        return [
            'sections' => $this->normalizeMap(self::SECTION_KEYS, $sections, $defaults['sections']),
            'groups' => $this->normalizeMap(self::GROUP_KEYS, $groups, $defaults['groups']),
        ];
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $source
     * @param  array<string, bool>  $fallback
     * @return array<string, bool>
     */
    private function normalizeMap(array $keys, array $source, array $fallback): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = array_key_exists($key, $source)
                ? (bool) $source[$key]
                : ($fallback[$key] ?? true);
        }

        return $out;
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, bool>
     */
    private function allTrue(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = true;
        }

        return $out;
    }

    /**
     * @param  array{sections: array<string, bool>, groups: array<string, bool>}  $a
     * @param  array{sections: array<string, bool>, groups: array<string, bool>}  $b
     * @return array{sections: array<string, bool>, groups: array<string, bool>}
     */
    private function intersectPolicy(array $a, array $b): array
    {
        $sections = [];
        foreach (self::SECTION_KEYS as $key) {
            $sections[$key] = ($a['sections'][$key] ?? true) && ($b['sections'][$key] ?? true);
        }

        $groups = [];
        foreach (self::GROUP_KEYS as $key) {
            $groups[$key] = ($a['groups'][$key] ?? true) && ($b['groups'][$key] ?? true);
        }

        return ['sections' => $sections, 'groups' => $groups];
    }
}
