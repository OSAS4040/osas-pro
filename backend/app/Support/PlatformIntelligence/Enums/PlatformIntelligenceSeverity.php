<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

/**
 * Canonical severity for signals and incidents (single vocabulary).
 */
enum PlatformIntelligenceSeverity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
