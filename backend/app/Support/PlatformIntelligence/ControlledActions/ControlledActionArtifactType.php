<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActions;

/**
 * Persisted artifact kinds (contract action_type) — distinct from incident lifecycle.
 */
enum ControlledActionArtifactType: string
{
    case FollowUp = 'follow_up';
    case HumanReviewRequest = 'human_review_request';
    case InternalTaskReference = 'internal_task_reference';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
