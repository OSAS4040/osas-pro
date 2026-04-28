<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

enum PlatformIncidentEscalationState: string
{
    case None = 'none';
    case Pending = 'pending';
    case Escalated = 'escalated';
    case Contained = 'contained';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
