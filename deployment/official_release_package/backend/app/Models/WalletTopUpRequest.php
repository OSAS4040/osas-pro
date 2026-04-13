<?php

namespace App\Models;

use App\Enums\WalletTopUpPaymentMethod;
use App\Enums\WalletTopUpRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopUpRequest extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id', 'requested_by',
        'target', 'amount', 'currency', 'payment_method', 'reference_number',
        'receipt_path', 'status', 'notes_from_customer', 'review_notes',
        'reviewed_by', 'reviewed_at', 'approved_wallet_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'status' => WalletTopUpRequestStatus::class,
        'payment_method' => WalletTopUpPaymentMethod::class,
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedWalletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'approved_wallet_transaction_id');
    }
}
