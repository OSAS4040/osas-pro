<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'billing_flow_type', 'customer_visible',
        'work_order_number_snapshot', 'vehicle_plate_snapshot',
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
        'customer_visible' => 'boolean',
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

    /**
     * Invoices that may appear in the customer (or fleet) portal and tenant invoice APIs for those actors.
     * Internal / provider-to-platform flows stay hidden even if the caller mistakenly has invoices.view.
     */
    public function scopeCustomerPortalVisible(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q): void {
                $q->whereNull('billing_flow_type')
                    ->orWhere('billing_flow_type', 'platform_to_customer');
            })
            ->where(function (Builder $q): void {
                $q->whereNull('customer_visible')
                    ->orWhere('customer_visible', true);
            });
    }

    public function isCustomerPortalVisible(): bool
    {
        $flow = $this->billing_flow_type;
        if ($flow !== null && (string) $flow !== 'platform_to_customer') {
            return false;
        }

        return $this->customer_visible !== false;
    }

    /**
     * Tenant KPI / revenue reports: omit internal provider→platform settlement legs so totals are not
     * double-counted alongside the paired platform→customer invoice for the same work order.
     */
    public function scopeExcludingProviderPlatformSettlement(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('billing_flow_type')
                ->orWhere('billing_flow_type', '!=', 'provider_to_platform');
        });
    }

    /**
     * وضع شريك التنفيذ: لا تُعرض في واجهات الورشة فواتير المنصّة للعميل (platform_to_customer).
     */
    public function scopeOmitPlatformToCustomerForWorkshopPartner(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('billing_flow_type')
                ->orWhere('billing_flow_type', '<>', 'platform_to_customer');
        });
    }
}
