<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

/**
 * Where a {@see PlatformSignalContract} originated — not the same as incident source_signals (keys).
 */
enum PlatformSignalSourceType: string
{
    case Finance = 'finance';
    case Operations = 'operations';
    case Adoption = 'adoption';
    case Compliance = 'compliance';
    case Integrations = 'integrations';
    case Governance = 'governance';
    case Intelligence = 'intelligence';
    case System = 'system';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
