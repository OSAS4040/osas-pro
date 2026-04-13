<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Actions\Reporting\ResolveReportingContextAction;
use App\Models\User;
use App\Reporting\Queries\CustomerPulseSummaryQuery;
use App\Reporting\ReportingApiEnvelope;
use App\Reporting\ReportingDateRange;
use Illuminate\Http\Exceptions\HttpResponseException;

final class CustomerPulseSummaryReporter
{
    public function __construct(
        private readonly ResolveReportingContextAction $resolveContext,
        private readonly CustomerPulseSummaryQuery $query,
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
        if ($context->customerId === null) {
            throw new HttpResponseException(response()->json([
                'message'  => 'customer_id is required for this report.',
                'trace_id' => app('trace_id'),
            ], 422));
        }

        $includeFinancial = $actor->hasPermission('reports.financial.view');
        $payload = $this->query->execute($context, $period, $includeFinancial);

        return ReportingApiEnvelope::success(
            reportId: 'customer.pulse_summary',
            version: 1,
            period: $period,
            appliedFilters: $context->toFilterSnapshot(),
            data: [
                'summary'   => $payload['summary'],
                'breakdown' => $payload['breakdown'],
            ],
            meta: [
                'financial_metrics_included' => $includeFinancial,
                'read_only'                  => true,
                'filters_applied'            => $context->toFilterSnapshot(),
                'query_kind'                 => 'aggregate',
            ],
        );
    }
}
