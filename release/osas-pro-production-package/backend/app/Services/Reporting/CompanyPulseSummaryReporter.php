<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Actions\Reporting\ResolveReportingContextAction;
use App\Models\User;
use App\Reporting\Queries\CompanyPulseSummaryQuery;
use App\Reporting\ReportingApiEnvelope;
use App\Reporting\ReportingDateRange;

final class CompanyPulseSummaryReporter
{
    public function __construct(
        private readonly ResolveReportingContextAction $resolveContext,
        private readonly CompanyPulseSummaryQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function build(User $actor, array $validated): array
    {
        $period = ReportingDateRange::fromDateStrings(
            (string) $validated['from'],
            (string) $validated['to'],
        );

        $context = ($this->resolveContext)($actor, $validated);
        $includeFinancial = $actor->hasPermission('reports.financial.view');

        $payload = $this->query->execute($context, $period, $includeFinancial);

        return ReportingApiEnvelope::success(
            reportId: 'company.pulse_summary',
            version: 1,
            period: $period,
            appliedFilters: $context->toFilterSnapshot(),
            data: [
                'summary'   => $payload['summary'],
                'breakdown' => $payload['breakdown'],
            ],
            meta: array_merge($payload['meta'], [
                'query_kind' => 'aggregate',
            ]),
        );
    }
}
