<?php

namespace App\Modules\SubscriptionsV2\Enums;

enum ReconciliationMatchType: string
{
    case Auto    = 'auto';
    case Partial = 'partial';
    case Manual  = 'manual';
}
