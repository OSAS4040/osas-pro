<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'product_id',
        'created_by_user_id', 'unit_id',
        'type', 'quantity', 'unit_cost',
        'quantity_before', 'quantity_after',
        'reference_type', 'reference_id',
        'original_movement_id', 'reversal_movement_id',
        'trace_id', 'note', 'created_at',
    ];

    protected $casts = [
        'quantity'        => 'decimal:4',
        'unit_cost'       => 'decimal:4',
        'quantity_before' => 'decimal:4',
        'quantity_after'  => 'decimal:4',
        'created_at'      => 'datetime',
        'type'            => StockMovementType::class,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('StockMovement records are immutable — no updates allowed.');
        });

        static::deleting(function () {
            throw new \RuntimeException('StockMovement records are immutable — no deletes allowed.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function originalMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_movement_id');
    }

    public static function currentStockForProduct(int $companyId, int $branchId, int $productId): float
    {
        return (float) static::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->sum('quantity');
    }
}
