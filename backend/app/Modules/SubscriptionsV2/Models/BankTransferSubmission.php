<?php

namespace App\Modules\SubscriptionsV2\Models;

use App\Models\User;
use App\Modules\SubscriptionsV2\Enums\BankTransferReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransferSubmission extends Model
{
    protected $fillable = [
        'payment_order_id',
        'submitted_by',
        'amount',
        'transfer_date',
        'transfer_time',
        'bank_name',
        'sender_name',
        'sender_account_masked',
        'bank_reference',
        'receipt_path',
        'receipt_original_name',
        'status',
        'notes',
    ];

    protected $casts = [
        'status'        => BankTransferReviewStatus::class,
        'amount'        => 'decimal:2',
        'transfer_date' => 'date',
    ];

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
