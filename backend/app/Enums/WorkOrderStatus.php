<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Draft       = 'draft';
    case Pending     = 'pending';
    case InProgress  = 'in_progress';
    case OnHold      = 'on_hold';
    case Completed   = 'completed';
    case Delivered   = 'delivered';
    case Cancelled   = 'cancelled';
}
