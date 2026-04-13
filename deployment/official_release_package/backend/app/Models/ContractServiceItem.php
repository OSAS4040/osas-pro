<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractServiceItem extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'contract_id', 'service_id', 'branch_id',
        'unit_price', 'tax_rate', 'discount_amount',
        'applies_to_all_vehicles', 'vehicle_ids',
        'max_total_quantity', 'requires_internal_approval',
        'status', 'priority', 'notes',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'applies_to_all_vehicles' => 'boolean',
        'vehicle_ids' => 'array',
        'max_total_quantity' => 'decimal:4',
        'requires_internal_approval' => 'boolean',
        'priority' => 'integer',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
