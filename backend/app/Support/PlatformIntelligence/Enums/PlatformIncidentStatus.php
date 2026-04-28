<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

enum PlatformIncidentStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case UnderReview = 'under_review';
    case Escalated = 'escalated';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
