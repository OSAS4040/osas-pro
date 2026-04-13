<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Actions\Reporting\ResolveReportingContextAction;
use App\Intelligence\Assemblers\WorkOrderSummaryIntelligenceAssembler;
use App\Models\User;
use App\Reporting\Queries\WorkOrderOperationalSummaryQuery;
use App\Reporting\ReportingApiEnvelope;
use App\Reporting\ReportingDateRange;

/**
 * Orchestrates the operational work-order summary report (read-only).
 */
final class WorkOrderOperationalSummaryReporter
{
    public function __construct(
        private readonly ResolveReportingContextAction $resolveContext,
        private readonly WorkOrderOperationalSummaryQuery $query,
        private readonly WorkOrderSummaryIntelligenceAssembler $workOrderSummaryIntelligenceAssembler,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  validated request filters
     * @return array<string, mixed>
     */
    public function build(User $actor, array $filters): array
    {
        $period = ReportingDateRange::fromDateStrings(
            (string) $filters['from'],
            (string) $filters['to'],
        );

        $context = ($this->resolveContext)($actor, $filters);
        $rows = $this->query->execute($context, $period);

        $intelligence = $this->workOrderSummaryIntelligenceAssembler->assemble($rows);

        return ReportingApiEnvelope::success(
            reportId: 'operations.work_order_summary',
            version: 1,
            period: $period,
            appliedFilters: $context->toFilterSnapshot(),
            data: [
                'by_status' => $rows,
                'totals' => [
                    'work_orders' => array_sum(array_column($rows, 'count')),
                ],
                'intelligence' => $intelligence,
            ],
            meta: [
                'query_kind' => 'aggregate',
                'intelligence_version' => 'v1',
            ],
        );
    }
}
