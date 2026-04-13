<?php

namespace App\Enums;

enum CompanyFinancialModelStatus: string
{
    case PendingPlatformReview = 'pending_platform_review';
    case ApprovedPrepaid = 'approved_prepaid';
    case ApprovedCredit = 'approved_credit';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}
