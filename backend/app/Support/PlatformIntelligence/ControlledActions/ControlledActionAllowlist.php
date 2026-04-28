<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActions;

/**
 * Strict allowlist of controlled operations — no generic executor outside this set.
 */
final class ControlledActionAllowlist
{
    public const CREATE_FOLLOW_UP = 'create_follow_up';

    public const ASSIGN_FOLLOW_UP_OWNER = 'assign_follow_up_owner';

    public const REQUEST_HUMAN_REVIEW = 'request_human_review';

    public const SCHEDULE_FOLLOW_UP_WINDOW = 'schedule_follow_up_window';

    public const LINK_INCIDENT_TO_INTERNAL_TASK_REFERENCE = 'link_incident_to_internal_task_reference';

    public const MARK_FOLLOW_UP_COMPLETED = 'mark_follow_up_completed';

    public const CANCEL_FOLLOW_UP_WITH_REASON = 'cancel_follow_up_with_reason';

    public const REOPEN_FOLLOW_UP_IF_NEEDED = 'reopen_follow_up_if_needed';

    /** @return list<string> */
    public static function allOperations(): array
    {
        return [
            self::CREATE_FOLLOW_UP,
            self::ASSIGN_FOLLOW_UP_OWNER,
            self::REQUEST_HUMAN_REVIEW,
            self::SCHEDULE_FOLLOW_UP_WINDOW,
            self::LINK_INCIDENT_TO_INTERNAL_TASK_REFERENCE,
            self::MARK_FOLLOW_UP_COMPLETED,
            self::CANCEL_FOLLOW_UP_WITH_REASON,
            self::REOPEN_FOLLOW_UP_IF_NEEDED,
        ];
    }

    public static function isAllowedOperation(string $operation): bool
    {
        return in_array($operation, self::allOperations(), true);
    }
}
