<?php

namespace App\Enums;

enum WalletTopUpRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ReturnedForRevision = 'returned_for_revision';
}
