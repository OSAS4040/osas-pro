<?php

namespace App\Modules\SubscriptionsV2\Enums;

enum SubscriptionLifecycleStatus: string
{
    case Pending                  = 'pending';
    case Active                   = 'active';
    case Expired                  = 'expired';
    case Suspended                = 'suspended';
    case ScheduledForDowngrade    = 'scheduled_for_downgrade';
}
