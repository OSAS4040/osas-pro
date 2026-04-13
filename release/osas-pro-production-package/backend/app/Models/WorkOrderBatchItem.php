<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderBatchItem extends Model
{
    protected $fillable = [
        'work_order_batch_id', 'vehicle_id', 'customer_id', 'work_order_id',
        'status', 'error_message', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WorkOrderBatch::class, 'work_order_batch_id');
    }
}
