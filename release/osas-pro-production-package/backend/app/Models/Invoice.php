<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id', 'vehicle_id',
        'created_by_user_id',
        'invoice_number', 'type', 'status', 'customer_type',
        'source_type', 'source_id',
        'subtotal', 'discount_amount', 'tax_amount', 'total',
        'paid_amount', 'due_amount', 'currency',
        'idempotency_key', 'invoice_hash', 'previous_invoice_hash',
        'invoice_counter', 'zatca_status', 'external_sync_status',
        'trace_id', 'notes', 'issued_at', 'due_at', 'version',
        'invoice_id', 'vat_type', 'media',
    ];

    protected $casts = [
        'status'          => InvoiceStatus::class,
        'subtotal'        => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount'      => 'decimal:4',
        'total'           => 'decimal:4',
        'paid_amount'     => 'decimal:4',
        'due_amount'      => 'decimal:4',
        'issued_at'       => 'datetime',
        'due_at'          => 'datetime',
        'media'           => 'array',
    ];

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    public function isEditable(): bool
    {
        return in_array($this->status->value, ['draft', 'pending']);
    }
}
