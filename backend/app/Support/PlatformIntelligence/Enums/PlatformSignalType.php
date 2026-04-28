<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

/**
 * Taxonomy for {@see PlatformSignalContract::signal_type} — orthogonal to {@see PlatformSignalSourceType}.
 */
enum PlatformSignalType: string
{
    case MetricThreshold = 'metric_threshold';
    case Trend = 'trend';
    case Anomaly = 'anomaly';
    case Rule = 'rule';
    case Manual = 'manual';
    case Correlation = 'correlation';
    case Composite = 'composite';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
