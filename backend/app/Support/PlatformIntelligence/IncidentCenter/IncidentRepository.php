<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\IncidentCenter;

use App\Models\PlatformIncident;
use App\Models\PlatformIncidentLifecycleEvent;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use Illuminate\Database\Eloquent\Collection;

final class IncidentRepository
{
    /**
     * @return Collection<int, PlatformIncident>
     */
    public function listOrdered(array $filters = []): Collection
    {
        $q = PlatformIncident::query();

        if (($filters['status'] ?? '') !== '') {
            $q->where('status', (string) $filters['status']);
        }
        if (($filters['severity'] ?? '') !== '') {
            $q->where('severity', (string) $filters['severity']);
        }
        if (($filters['incident_type'] ?? '') !== '') {
            $q->where('incident_type', (string) $filters['incident_type']);
        }
        if (($filters['owner'] ?? '') !== '') {
            $q->where('owner', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['owner']).'%');
        }
        if (($filters['escalation_state'] ?? '') !== '') {
            $q->where('escalation_state', (string) $filters['escalation_state']);
        }
        if (($filters['company_id'] ?? '') !== '' && $filters['company_id'] !== null) {
            $cid = $filters['company_id'];
            $q->where(function ($q2) use ($cid) {
                $q2->whereJsonContains('affected_companies', (int) $cid)
                    ->orWhereJsonContains('affected_companies', (string) $cid);
            });
        }
        if (($filters['fresh_hours'] ?? '') !== '' && is_numeric($filters['fresh_hours'])) {
            $h = (int) $filters['fresh_hours'];
            if ($h > 0) {
                $q->where('last_status_change_at', '>=', now()->subHours($h));
            }
        }

        $q->orderByRaw("CASE severity WHEN 'critical' THEN 5 WHEN 'high' THEN 4 WHEN 'medium' THEN 3 WHEN 'low' THEN 2 WHEN 'info' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('last_status_change_at')
            ->orderBy('incident_key');

        return $q->get();
    }

    public function findByIncidentKey(string $incidentKey): ?PlatformIncident
    {
        return PlatformIncident::query()->where('incident_key', $incidentKey)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timelineFor(string $incidentKey): array
    {
        $rows = PlatformIncidentLifecycleEvent::query()
            ->where('incident_key', $incidentKey)
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => $r->id,
                'event_type' => $r->event_type,
                'prior_status' => $r->prior_status,
                'next_status' => $r->next_status,
                'prior_escalation_state' => $r->prior_escalation_state,
                'next_escalation_state' => $r->next_escalation_state,
                'prior_owner' => $r->prior_owner,
                'next_owner' => $r->next_owner,
                'reason' => $r->reason,
                'actor_user_id' => $r->actor_user_id,
                'created_at' => $r->created_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    public function saveModel(PlatformIncident $row): void
    {
        $row->save();
    }

    public function createModel(PlatformIncident $row): PlatformIncident
    {
        $row->save();

        return $row;
    }
}
