<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\DecisionLog;

use App\Models\PlatformDecisionLogEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class DecisionLogRepository
{
    /**
     * Stable ordering: oldest first for narrative audit read, tie-break by decision_id.
     *
     * @return LengthAwarePaginator<int, PlatformDecisionLogEntry>
     */
    public function paginateByIncidentKey(
        string $incidentKey,
        int $perPage,
        ?string $decisionTypeFilter,
    ): LengthAwarePaginator {
        $q = PlatformDecisionLogEntry::query()->where('incident_key', $incidentKey);

        if ($decisionTypeFilter !== null && $decisionTypeFilter !== '') {
            $q->where('decision_type', $decisionTypeFilter);
        }

        return $q
            ->orderBy('created_at', 'asc')
            ->orderBy('decision_id', 'asc')
            ->paginate($perPage);
    }

    /**
     * @return list<PlatformDecisionLogEntry>
     */
    public function listAllForIncidentOrdered(string $incidentKey, int $limit): array
    {
        /** @var Builder<PlatformDecisionLogEntry> $q */
        $q = PlatformDecisionLogEntry::query()
            ->where('incident_key', $incidentKey)
            ->orderBy('created_at', 'asc')
            ->orderBy('decision_id', 'asc')
            ->limit(max(1, min($limit, 500)));

        return $q->get()->all();
    }
}
