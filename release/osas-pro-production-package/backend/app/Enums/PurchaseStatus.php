<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Pending   = 'pending';
    case Ordered   = 'ordered';
    case Partial   = 'partial';
    case Received  = 'received';
    case Cancelled = 'cancelled';
}
