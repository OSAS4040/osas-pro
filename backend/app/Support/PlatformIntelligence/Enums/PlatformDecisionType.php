<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Enums;

/**
 * Decision log taxonomy — distinct from {@see PlatformSignalSourceType} and incident lifecycle.
 */
enum PlatformDecisionType: string
{
    case Observation = 'observation';
    case Escalation = 'escalation';
    case FalsePositive = 'false_positive';
    case Monitor = 'monitor';
    case Closure = 'closure';
    case ActionApproved = 'action_approved';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
