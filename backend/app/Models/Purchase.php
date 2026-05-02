<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use HasFactory, HasTenantScope, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'supplier_id', 'created_by_user_id',
        'source_type', 'source_id', 'billing_flow_type',
        'reference_number', 'status', 'subtotal', 'discount_amount', 'tax_amount',
        'total', 'paid_amount', 'currency', 'notes', 'trace_id',
        'expected_at', 'received_at', 'version', 'document_attachments',
    ];

    protected $casts = [
        'document_attachments' => 'array',
        'status' => PurchaseStatus::class,
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'expected_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function purchaseClaims(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseClaim::class, 'purchase_claim_purchase')->withTimestamps();
    }

    /**
     * مشتريات التسوية من المنصّة للمزوّد — لا تُعرض لجهة العميل/الأسطول في البوابات.
     */
    public function scopeOmitPlatformProviderSettlement(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('billing_flow_type')
                ->orWhere('billing_flow_type', '<>', 'platform_to_provider_purchase');
        });
    }
}
