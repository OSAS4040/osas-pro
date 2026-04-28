<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending     = 'pending';
    case Active      = 'active';
    case PastDue     = 'past_due';
    case GracePeriod = 'grace_period';
    case Suspended   = 'suspended';
    case Expired     = 'expired';
}
