<?php

declare(strict_types=1);

namespace App\Intelligence\Assemblers;

use App\Intelligence\Services\AttentionEngine;
use App\Intelligence\Services\IntelligenceEvaluator;
use App\Intelligence\Support\LegacyAttentionAdapter;
use App\Models\Company;

/**
 * Company hub intelligence block (read-only).
 */
final class CompanyIntelligenceAssembler
{
    public function __construct(
        private readonly IntelligenceEvaluator $evaluator,
        private readonly AttentionEngine $attentionEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $summary
     * @return array{intelligence: array<string, mixed>, attention_items_legacy: list<array{code: string, severity: string, message: string}>}
     */
    public function assemble(Company $company, array $raw, array $summary, bool $includeFinancial): array
    {
        $statusVal = $company->status instanceof \BackedEnum ? $company->status->value : (string) ($company->status ?? '');
        $companyOperational = $company->is_active && $statusVal === 'active';

        $eval = $this->evaluator->evaluateCompany($companyOperational, $raw, $summary, $includeFinancial);
        $attentionDtos = $this->attentionEngine->forCompany($company, $raw, $eval['health_status']);
        $attention = array_map(static fn ($d) => $d->toArray(), $attentionDtos);

        $intelligence = [
            'health_status' => $eval['health_status'],
            'indicators' => $eval['indicators'],
            'attention_items' => $attention,
        ];

        return [
            'intelligence' => $intelligence,
            'attention_items_legacy' => LegacyAttentionAdapter::toLegacy($attention),
        ];
    }
}
