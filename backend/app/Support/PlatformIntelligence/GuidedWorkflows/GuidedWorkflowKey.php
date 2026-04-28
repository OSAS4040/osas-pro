<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\GuidedWorkflows;

enum GuidedWorkflowKey: string
{
    case AcknowledgeAssign = 'acknowledge_assign';
    case UnderReviewDecision = 'under_review_decision';
    case EscalateDecision = 'escalate_decision';
    case MonitorTransition = 'monitor_transition';
    case MonitorWithDecision = 'monitor_with_decision';
    case ResolveClosure = 'resolve_closure';
    case CloseFinal = 'close_final';
    case FalsePositive = 'false_positive';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
