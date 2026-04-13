<?php

namespace App\Models;

use App\Enums\WorkOrderCancellationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderCancellationRequest extends Model
{
    protected $fillable = [
        'uuid', 'company_id', 'work_order_id', 'requested_by_user_id', 'reason',
        'status', 'restoration_work_order_status', 'reviewed_by_user_id', 'reviewed_at',
        'review_notes', 'support_ticket_id',
    ];

    protected $casts = [
        'status' => WorkOrderCancellationRequestStatus::class,
        'reviewed_at' => 'datetime',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function supportTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }
}
