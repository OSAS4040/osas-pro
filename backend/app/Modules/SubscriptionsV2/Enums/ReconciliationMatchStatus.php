<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Enums;

enum ReconciliationMatchStatus: string
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Rejected  = 'rejected';
}
