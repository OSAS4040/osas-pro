<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActions;

/**
 * Granular IAM keys for controlled actions — enforced in executor + route middleware.
 */
final class ControlledActionPermissionMatrix
{
    public const VIEW = 'platform.intelligence.controlled_actions.view';

    public const CREATE_FOLLOW_UP = 'platform.intelligence.controlled_actions.create_follow_up';

    public const REQUEST_HUMAN_REVIEW = 'platform.intelligence.controlled_actions.request_human_review';

    public const LINK_TASK_REFERENCE = 'platform.intelligence.controlled_actions.link_task_reference';

    public const ASSIGN_OWNER = 'platform.intelligence.controlled_actions.assign_owner';

    public const SCHEDULE = 'platform.intelligence.controlled_actions.schedule';

    public const COMPLETE = 'platform.intelligence.controlled_actions.complete';

    public const CANCEL = 'platform.intelligence.controlled_actions.cancel';

    public const REOPEN = 'platform.intelligence.controlled_actions.reopen';

    public const BASE_INCIDENT_READ = 'platform.intelligence.incidents.read';
}
