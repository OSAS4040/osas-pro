<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\User;
use App\Reporting\Queries\PlatformPulseSummaryQuery;
use App\Reporting\ReportingApiEnvelope;
use App\Reporting\ReportingDateRange;

final class PlatformPulseSummaryReporter
{
    public function __construct(
        private readonly PlatformPulseSummaryQuery $query,
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

        $payload = $this->query->execute($period);

        return ReportingApiEnvelope::success(
            reportId: 'platform.pulse_summary',
            version: 1,
            period: $period,
            appliedFilters: [
                'scope'    => 'platform',
                'actor_id' => $actor->id,
            ],
            data: $payload,
            meta: [
                'query_kind' => 'aggregate',
            ],
        );
    }
}
