<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

enum PlatformIncidentOwnershipState: string
{
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case Reassigned = 'reassigned';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
