<?php

namespace App\Modules\SubscriptionsV2\Models;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentOrder extends Model
{
    protected $fillable = [
        'company_id',
        'plan_id',
        'amount',
        'vat',
        'total',
        'currency',
        'reference_code',
        'status',
        'expires_at',
        'approved_at',
        'approved_by',
        'created_by',
    ];

    protected $casts = [
        'status'       => PaymentOrderStatus::class,
        'amount'       => 'decimal:2',
        'vat'          => 'decimal:2',
        'total'        => 'decimal:2',
        'expires_at'   => 'datetime',
        'approved_at'  => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function bankTransferSubmissions(): HasMany
    {
        return $this->hasMany(BankTransferSubmission::class);
    }

    public function reconciliationMatches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_order_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasConfirmedMatch(): bool
    {
        return $this->reconciliationMatches()
            ->where('status', ReconciliationMatchStatus::Confirmed)
            ->exists();
    }
}
