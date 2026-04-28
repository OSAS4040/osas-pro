<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Policy;

use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use InvalidArgumentException;

/**
 * Authoritative incident lifecycle edges for Platform Intelligence Operations.
 * UI and future write APIs must consult this policy — never ad-hoc string compares.
 */
final class PlatformIncidentStatusTransitionPolicy
{
    /**
     * @return list<array{0: PlatformIncidentStatus, 1: PlatformIncidentStatus}>
     */
    public static function allowedDirectedEdges(): array
    {
        return [
            [PlatformIncidentStatus::Open, PlatformIncidentStatus::Acknowledged],
            [PlatformIncidentStatus::Acknowledged, PlatformIncidentStatus::UnderReview],
            [PlatformIncidentStatus::UnderReview, PlatformIncidentStatus::Escalated],
            [PlatformIncidentStatus::UnderReview, PlatformIncidentStatus::Monitoring],
            [PlatformIncidentStatus::Escalated, PlatformIncidentStatus::Monitoring],
            [PlatformIncidentStatus::Escalated, PlatformIncidentStatus::Resolved],
            [PlatformIncidentStatus::Monitoring, PlatformIncidentStatus::Resolved],
            [PlatformIncidentStatus::Resolved, PlatformIncidentStatus::Closed],
        ];
    }

    public static function isAllowed(PlatformIncidentStatus $from, PlatformIncidentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        foreach (self::allowedDirectedEdges() as [$a, $b]) {
            if ($a === $from && $b === $to) {
                return true;
            }
        }

        return false;
    }

    public static function assertAllowed(PlatformIncidentStatus $from, PlatformIncidentStatus $to): void
    {
        if (! self::isAllowed($from, $to)) {
            throw new InvalidArgumentException(sprintf(
                'Incident status transition not allowed: %s -> %s',
                $from->value,
                $to->value,
            ));
        }
    }

    /**
     * @return list<PlatformIncidentStatus>
     */
    public static function allowedTargets(PlatformIncidentStatus $from): array
    {
        $out = [$from];
        foreach (self::allowedDirectedEdges() as [$a, $b]) {
            if ($a === $from) {
                $out[] = $b;
            }
        }

        return array_values(array_unique($out, SORT_REGULAR));
    }
}
