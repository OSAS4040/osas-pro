<?php

namespace App\Services;

use App\Models\PolicyRule;
use Illuminate\Support\Facades\Cache;

/**
 * PolicyEngine — evaluates a single rule code against a given value.
 *
 * Supported operators: lte | gte | eq | neq | in | not_in | between
 * Supported actions:   require_approval | block | alert
 */
class PolicyEngine
{
    /**
     * Evaluate a policy rule for a company.
     *
     * @param  int    $companyId
     * @param  string $code       e.g. 'discount.max'
     * @param  mixed  $input      the value to check
     * @param  array  $context    extra context (entity_type, entity_id, role…)
     * @return array{passed: bool, action: string|null, rule: PolicyRule|null}
     */
    public function evaluate(int $companyId, string $code, mixed $input, array $context = []): array
    {
        $rule = $this->findRule($companyId, $code, $context);

        if (!$rule) {
            return ['passed' => true, 'action' => null, 'rule' => null];
        }

        $passed = $this->check($rule->operator, $input, $rule->value);

        return [
            'passed' => $passed,
            'action' => $passed ? null : $rule->action,
            'rule'   => $rule,
        ];
    }

    private function findRule(int $companyId, string $code, array $context): ?PolicyRule
    {
        $cacheKey = "policy:{$companyId}:{$code}";

        return Cache::remember($cacheKey, 120, function () use ($companyId, $code, $context) {
            $query = PolicyRule::where('company_id', $companyId)
                ->where('code', $code)
                ->where('is_active', true);

            // Prefer entity-specific rule over global
            if (!empty($context['entity_type']) && !empty($context['entity_id'])) {
                $specific = (clone $query)
                    ->where('entity_type', $context['entity_type'])
                    ->where('entity_id', $context['entity_id'])
                    ->first();
                if ($specific) return $specific;
            }

            return $query->whereNull('entity_type')->orWhere('entity_type', 'global')->first();
        });
    }

    private function check(string $operator, mixed $input, mixed $threshold): bool
    {
        $threshold = is_array($threshold) ? $threshold : [$threshold];

        return match ($operator) {
            'lte'     => (float) $input <= (float) ($threshold[0] ?? 0),
            'gte'     => (float) $input >= (float) ($threshold[0] ?? 0),
            'eq'      => $input == ($threshold[0] ?? null),
            'neq'     => $input != ($threshold[0] ?? null),
            'in'      => in_array($input, $threshold),
            'not_in'  => !in_array($input, $threshold),
            'between' => (float) $input >= (float) ($threshold[0] ?? 0)
                      && (float) $input <= (float) ($threshold[1] ?? PHP_INT_MAX),
            default   => true,
        };
    }

    public function clearCache(int $companyId, string $code): void
    {
        Cache::forget("policy:{$companyId}:{$code}");
    }
}
