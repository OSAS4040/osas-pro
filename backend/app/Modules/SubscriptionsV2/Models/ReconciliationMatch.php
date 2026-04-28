<?php

namespace App\Modules\SubscriptionsV2\Models;

use App\Models\User;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationMatch extends Model
{
    protected $fillable = [
        'payment_order_id',
        'bank_transaction_id',
        'score',
        'match_type',
        'status',
        'matched_by',
        'decision_notes',
    ];

    protected $casts = [
        'score'      => 'decimal:4',
        'match_type' => ReconciliationMatchType::class,
        'status'     => ReconciliationMatchStatus::class,
    ];

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }
}
