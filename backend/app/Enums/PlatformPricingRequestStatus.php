<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformPricingRequestStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case UnderReview = 'under_review';
    case ReviewedRecommended = 'reviewed_recommended';
    case PendingPlatformApproval = 'pending_platform_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReturnedForEdit = 'returned_for_edit';
}
