<?php

declare(strict_types=1);

namespace App\Reporting;

/**
 * Unified JSON shape for reporting endpoints (read-only).
 */
final class ReportingApiEnvelope
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $appliedFilters
     */
    public static function success(
        string $reportId,
        int $version,
        ReportingDateRange $period,
        array $appliedFilters,
        array $data,
        array $meta = [],
    ): array {
        return [
            'report' => [
                'id' => $reportId,
                'version' => $version,
                'generated_at' => now()->toIso8601String(),
                'period' => $period->toPeriodArray(),
                'filters' => $appliedFilters,
                'read_only' => true,
                'export' => [
                    'supported' => (bool) config('reporting.export.enabled', false),
                    'formats_supported' => (array) config('reporting.export.formats_supported', ['csv']),
                ],
            ],
            'data' => $data,
            'meta' => array_merge([
                'query_kind' => 'aggregate',
            ], $meta),
            'trace_id' => app('trace_id'),
        ];
    }
}
