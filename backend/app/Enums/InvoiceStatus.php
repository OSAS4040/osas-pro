<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft     = 'draft';
    case Pending   = 'pending';
    case Paid      = 'paid';
    case PartialPaid = 'partial_paid';
    case Cancelled = 'cancelled';
    case Refunded  = 'refunded';
}
