<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActions;

/**
 * Controlled action lifecycle — never mutates incident rows.
 */
enum ControlledActionStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Canceled = 'canceled';
    case Blocked = 'blocked';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
