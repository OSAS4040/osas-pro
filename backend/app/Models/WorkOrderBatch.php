<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderBatch extends Model
{
    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'created_by_user_id', 'status', 'notes',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderBatchItem::class, 'work_order_batch_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
