<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Actions\Reporting\ResolveReportingContextAction;
use App\Intelligence\Assemblers\OperationsFeedIntelligenceAssembler;
use App\Models\User;
use App\Reporting\Operations\OperationFeedItemPresenter;
use App\Reporting\Queries\GlobalOperationsFeedQuery;
use App\Reporting\ReportingApiEnvelope;
use App\Reporting\ReportingDateRange;
use Illuminate\Http\Exceptions\HttpResponseException;

final class GlobalOperationsFeedReporter
{
    public function __construct(
        private readonly ResolveReportingContextAction $resolveContext,
        private readonly GlobalOperationsFeedQuery $query,
        private readonly OperationFeedItemPresenter $presenter,
        private readonly OperationsFeedIntelligenceAssembler $operationsFeedIntelligenceAssembler,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function build(User $actor, array $validated, ?int $exportRowCap = null): array
    {
        $period = ReportingDateRange::fromDateStrings(
            (string) $validated['from'],
            (string) $validated['to'],
        );

        $requestedCompanyId = isset($validated['company_id']) ? (int) $validated['company_id'] : null;
        if ($requestedCompanyId !== null && $requestedCompanyId > 0 && $requestedCompanyId !== (int) $actor->company_id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid company scope for this feed.',
                'trace_id' => app('trace_id'),
            ], 422));
        }

        $context = ($this->resolveContext)($actor, $validated);

        $hasFinancialPermission = $actor->hasPermission('reports.financial.view');
        $includeFinancial = $hasFinancialPermission && filter_var($validated['include_financial'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $types = $validated['types'] ?? null;
        if (is_string($types)) {
            $types = [$types];
        }
        $statuses = $validated['statuses'] ?? null;
        if (is_string($statuses)) {
            $statuses = [$statuses];
        }

        $feedFilters = [
            'types' => is_array($types) ? $types : null,
            'statuses' => is_array($statuses) ? $statuses : null,
            'attention_level' => $validated['attention_level'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? config('reporting.global_feed_default_per_page', 25)),
            'include_financial' => $includeFinancial,
        ];

        if ($exportRowCap !== null) {
            $cap = max(1, min($exportRowCap, (int) config('reporting.export.max_rows', 500)));
            $feedFilters['page'] = 1;
            $feedFilters['per_page'] = $cap;
        }

        $payload = $this->query->execute($context, $period, $feedFilters, $hasFinancialPermission);

        $financialVisible = $hasFinancialPermission && $includeFinancial;
        $items = [];
        foreach ($payload['items'] as $row) {
            $items[] = $this->presenter->present($row, $financialVisible);
        }

        $perPage = max(1, (int) $feedFilters['per_page']);
        $page = max(1, (int) $feedFilters['page']);
        $total = (int) $payload['total'];
        $lastPage = (int) max(1, (int) ceil($total / $perPage));

        $snapshot = array_merge($context->toFilterSnapshot(), [
            'from' => (string) $validated['from'],
            'to' => (string) $validated['to'],
            'types' => $feedFilters['types'],
            'statuses' => $feedFilters['statuses'],
            'attention_level' => $feedFilters['attention_level'],
            'include_financial' => $includeFinancial,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        $sourceEntities = ['work_order', 'invoice', 'ticket'];
        if ($includeFinancial && $hasFinancialPermission) {
            $sourceEntities[] = 'payment';
        }

        $intelligence = $this->operationsFeedIntelligenceAssembler->assemble($payload['summary'], $financialVisible);

        $meta = [
            'query_kind' => 'list',
            'financial_metrics_included' => $financialVisible,
            'read_only' => true,
            'intelligence_version' => 'v1',
            'filters_applied' => $snapshot,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
            'source_entities_included' => $sourceEntities,
            'generated_at' => now()->toIso8601String(),
        ];
        if ($exportRowCap !== null) {
            $meta['export'] = true;
            $meta['export_row_cap'] = $perPage;
            $meta['export_total_matching_rows'] = $total;
            $meta['export_truncated'] = $total > $perPage;
        }

        return ReportingApiEnvelope::success(
            reportId: 'operations.global_feed',
            version: 1,
            period: $period,
            appliedFilters: $snapshot,
            data: [
                'summary' => $payload['summary'],
                'items' => $items,
                'intelligence' => $intelligence,
            ],
            meta: $meta,
        );
    }
}
