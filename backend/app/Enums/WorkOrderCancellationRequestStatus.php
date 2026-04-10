<?php

namespace App\Enums;

enum WorkOrderCancellationRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
