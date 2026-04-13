<?php

namespace App\Services\Config;

use App\Models\ConfigSetting;

class ConfigResolverService
{
    /**
     * Resolve with precedence:
     * system -> plan -> vertical -> company -> branch
     */
    public function resolve(string $key, array $context = []): mixed
    {
        $candidates = [
            ['scope_type' => 'system', 'scope_key' => 'system'],
            ['scope_type' => 'plan', 'scope_key' => (string) ($context['plan'] ?? '')],
            ['scope_type' => 'vertical', 'scope_key' => (string) ($context['vertical'] ?? '')],
            ['scope_type' => 'company', 'scope_key' => (string) ($context['company_id'] ?? '')],
            ['scope_type' => 'branch', 'scope_key' => (string) ($context['branch_id'] ?? '')],
        ];

        $resolved = null;
        foreach ($candidates as $candidate) {
            if ($candidate['scope_key'] === '') {
                continue;
            }

            $row = ConfigSetting::query()
                ->where('scope_type', $candidate['scope_type'])
                ->where('scope_key', $candidate['scope_key'])
                ->where('config_key', $key)
                ->where('is_active', true)
                ->first();

            if ($row) {
                $resolved = $this->castValue($row->config_value, (string) ($row->value_type ?? 'string'));
            }
        }

        return $resolved;
    }

    public function resolveBool(string $key, array $context = [], bool $default = false): bool
    {
        $resolved = $this->resolve($key, $context);
        if ($resolved === null) {
            return $default;
        }

        return filter_var($resolved, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function castValue(mixed $raw, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'integer' => is_numeric($raw) ? (int) $raw : 0,
            'float' => is_numeric($raw) ? (float) $raw : 0.0,
            'json' => is_string($raw) ? json_decode($raw, true) : $raw,
            default => $raw,
        };
    }
}
