<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierContract extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'supplier_id', 'title', 'stored_path',
        'original_filename', 'mime_type', 'expires_at', 'notes', 'created_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
