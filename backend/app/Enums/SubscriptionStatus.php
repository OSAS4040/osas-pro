<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active      = 'active';
    case GracePeriod = 'grace_period';
    case Suspended   = 'suspended';
}
