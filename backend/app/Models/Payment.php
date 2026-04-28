<?php

namespace App\Models;

use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'invoice_id', 'payment_order_id', 'created_by_user_id',
        'method', 'payment_method', 'amount', 'currency', 'reference',
        'status', 'external_sync_status', 'external_reference',
        'original_payment_id', 'reversal_payment_id', 'trace_id', 'meta', 'created_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:4',
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public static function boot(): void
    {
        parent::boot();

        static::updating(function ($model) {
            $allowed = ['status', 'reversal_payment_id', 'external_sync_status'];
            if ($model->isDirty('invoice_id') && $model->getOriginal('invoice_id') === null) {
                $allowed[] = 'invoice_id';
            }
            $dirty   = array_keys($model->getDirty());
            $illegal = array_diff($dirty, $allowed);

            if (! empty($illegal)) {
                throw new \RuntimeException(
                    'Payment records are immutable. Illegal fields modified: ' . implode(', ', $illegal)
                );
            }
        });

        static::deleting(function () {
            throw new \RuntimeException('Payment records cannot be deleted.');
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentOrder()
    {
        return $this->belongsTo(PaymentOrder::class, 'payment_order_id');
    }

    public function originalPayment()
    {
        return $this->belongsTo(Payment::class, 'original_payment_id');
    }

    public function reversalPayment()
    {
        return $this->hasOne(Payment::class, 'original_payment_id');
    }
}
