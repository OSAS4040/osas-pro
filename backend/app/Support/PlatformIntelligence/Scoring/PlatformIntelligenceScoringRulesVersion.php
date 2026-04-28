<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Scoring;

use App\Services\Platform\PlatformAdminOverviewService;

/**
 * Logical version for severity/confidence rules + response ordering contract.
 * Bump when changing {@see SeverityScorer}, {@see ConfidenceScorer}, or {@see SignalResponseOrdering}.
 */
final class PlatformIntelligenceScoringRulesVersion
{
    public const VERSION = '1.0.0';

    /** ISO date when VERSION last changed (update together with VERSION). */
    public const RELEASE_DATE = '2026-04-14';

    /** Human-readable changelog line for operators / incident-candidate work. */
    public const CHANGELOG = '1.0.0: initial Priority 7 scoring; stable API ordering (severity desc, confidence desc, last_seen desc, signal_key asc).';

    public static function signalListOrderDescription(): string
    {
        return 'severity_desc,confidence_desc,last_seen_desc,signal_key_asc_tiebreak';
    }

    public static function overviewSnapshotTtlSeconds(): int
    {
        return PlatformAdminOverviewService::EXECUTIVE_OVERVIEW_CACHE_TTL_SECONDS;
    }
}
