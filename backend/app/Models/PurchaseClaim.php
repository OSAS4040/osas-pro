<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseClaim extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid',
        'company_id',
        'branch_id',
        'created_by_user_id',
        'status',
        'title',
        'description',
        'requested_amount',
        'admin_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
