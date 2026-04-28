<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

/**
 * How an entity links to another in operator-facing correlation (not lifecycle semantics).
 */
enum CorrelationRelationType: string
{
    /** Directly listed on the incident (e.g. source_signals). */
    case Causal = 'causal';
    /** Shared scope (companies/keys/time) without direct listing. */
    case Contextual = 'contextual';
    /** Structural derivation (candidate key matches materialized incident). */
    case Derived = 'derived';
    /** Same incident scope ordering (decisions, workflow runs). */
    case TimelineRelated = 'timeline_related';
}
