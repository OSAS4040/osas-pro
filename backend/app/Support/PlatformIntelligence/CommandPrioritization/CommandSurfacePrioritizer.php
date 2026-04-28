<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CommandPrioritization;

use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Stable, documented prioritization for command surface tiles (read-only ranking).
 */
final class CommandSurfacePrioritizer
{
    /**
     * @param  array<string, mixed>  $incidentRow
     */
    public static function incidentScore(array $incidentRow): int
    {
        $sev = self::severityWeight((string) ($incidentRow['severity'] ?? ''));
        $esc = ((string) ($incidentRow['escalation_state'] ?? '')) === 'escalated' ? 40 : 0;
        $open = ! in_array((string) ($incidentRow['status'] ?? ''), [
            PlatformIncidentStatus::Closed->value,
            PlatformIncidentStatus::Resolved->value,
        ], true) ? 25 : 0;
        $fresh = isset($incidentRow['last_status_change_at']) ? 5 : 0;

        return $sev + $esc + $open + $fresh;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function sortIncidentsStable(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $sa = self::incidentScore($a);
            $sb = self::incidentScore($b);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }

            // Tie-break: ascending lexicographic on incident_key (stable across refreshes).
            return strcmp((string) ($a['incident_key'] ?? ''), (string) ($b['incident_key'] ?? ''));
        });

        return array_values($rows);
    }

    private static function severityWeight(string $s): int
    {
        return match ($s) {
            PlatformIntelligenceSeverity::Critical->value => 100,
            PlatformIntelligenceSeverity::High->value => 80,
            PlatformIntelligenceSeverity::Medium->value => 50,
            PlatformIntelligenceSeverity::Low->value => 30,
            PlatformIntelligenceSeverity::Info->value => 10,
            default => 0,
        };
    }
}
