<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use App\Models\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id',
        'created_by_user_id', 'wallet_id', 'customer_wallet_id',
        'vehicle_id', 'type', 'amount', 'payment_mode',
        'balance_before', 'balance_after',
        'original_transaction_id', 'reversal_transaction_id',
        'reference_type', 'reference_id', 'invoice_id', 'payment_id',
        'idempotency_key', 'trace_id', 'note', 'created_at',
    ];

    protected $casts = [
        'type'         => WalletTransactionType::class,
        'amount'       => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after'  => 'decimal:4',
        'created_at'   => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('WalletTransaction records are immutable. Use a reversal instead.');
        });

        static::deleting(function () {
            throw new \RuntimeException('WalletTransaction records cannot be deleted. Use a reversal instead.');
        });
    }

    public function customerWallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'customer_wallet_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'original_transaction_id');
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'reversal_transaction_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Alias for `type` (enum) — used by tests and APIs expecting `transaction_type`.
     */
    public function getTransactionTypeAttribute(): ?WalletTransactionType
    {
        return $this->type;
    }
}
