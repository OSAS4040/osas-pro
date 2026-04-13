<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Draft = 'draft';

    /** Awaiting internal / manager approval before financial lock */
    case PendingManagerApproval = 'pending_manager_approval';

    /** Operationally and financially locked for direct edits; may proceed to execution */
    case Approved = 'approved';

    /** Cancellation workflow in progress — no edits */
    case CancellationRequested = 'cancellation_requested';

    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
