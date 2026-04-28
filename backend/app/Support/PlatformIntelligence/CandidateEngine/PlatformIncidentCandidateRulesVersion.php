<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

/**
 * Version stamp for candidate derivation rules (identity salt, ordering, suppression).
 */
final class PlatformIncidentCandidateRulesVersion
{
    public const VERSION = '1.0.0';

    public const RELEASE_DATE = '2026-04-14';

    public const CHANGELOG = 'Initial incident candidate layer: eligibility, deterministic grouping, severity/confidence rollup, suppression, explainability.';

    /** Salt for stable incident_key hashing — bump only when identity policy changes. */
    public const INCIDENT_KEY_SALT = 'pi_icand_key_v1';

    public static function candidateListOrderDescription(): string
    {
        return 'severity_desc,confidence_desc,last_seen_desc,incident_key_asc_tiebreak';
    }
}
