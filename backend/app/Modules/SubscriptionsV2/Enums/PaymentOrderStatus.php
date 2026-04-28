<?php

namespace App\Modules\SubscriptionsV2\Enums;

enum PaymentOrderStatus: string
{
    case PendingTransfer = 'pending_transfer';
    case AwaitingReview  = 'awaiting_review';
    case Matched         = 'matched';
    case Approved        = 'approved';
    case Rejected        = 'rejected';
    case Expired         = 'expired';
    case Cancelled       = 'cancelled';
}
