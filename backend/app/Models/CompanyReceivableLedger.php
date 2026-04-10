<?php

namespace App\Models;

use App\Enums\CompanyReceivableEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyReceivableLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'company_receivables_ledger';

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id', 'vehicle_id',
        'work_order_id', 'invoice_id', 'entry_type', 'amount', 'currency',
        'running_balance_company', 'idempotency_key', 'reference_type', 'reference_id', 'meta',
    ];

    protected $casts = [
        'entry_type' => CompanyReceivableEntryType::class,
        'amount' => 'decimal:4',
        'running_balance_company' => 'decimal:4',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
