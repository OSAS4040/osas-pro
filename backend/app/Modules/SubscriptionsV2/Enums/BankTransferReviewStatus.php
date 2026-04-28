<?php

namespace App\Modules\SubscriptionsV2\Enums;

enum BankTransferReviewStatus: string
{
    case Pending            = 'pending';
    case UnderReview        = 'under_review';
    case Approved           = 'approved';
    case Rejected           = 'rejected';
    case NeedsResubmission  = 'needs_resubmission';
}
