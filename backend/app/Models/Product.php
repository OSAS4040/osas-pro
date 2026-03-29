<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'created_by_user_id', 'category_id',
        'unit_id', 'purchase_unit_id',
        'name', 'name_ar', 'barcode', 'sku',
        'product_type',
        'sale_price', 'cost_price', 'tax_rate',
        'is_taxable', 'track_inventory', 'is_active', 'meta', 'version',
    ];

    protected $casts = [
        'sale_price'      => 'decimal:4',
        'cost_price'      => 'decimal:4',
        'tax_rate'        => 'decimal:2',
        'is_taxable'      => 'boolean',
        'track_inventory' => 'boolean',
        'is_active'       => 'boolean',
        'meta'            => 'array',
        'product_type'    => ProductType::class,
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function shouldTrackInventory(): bool
    {
        if (isset($this->track_inventory)) {
            return (bool) $this->track_inventory;
        }
        $type = $this->product_type instanceof ProductType ? $this->product_type : ProductType::from($this->product_type ?? 'physical');
        return $type->tracksInventory();
    }

    public function getStockForBranch(int $branchId): float
    {
        return (float) StockMovement::where('product_id', $this->id)
            ->where('branch_id', $branchId)
            ->sum('quantity');
    }
}
