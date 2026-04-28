<?php

namespace App\Modules\SubscriptionsV2\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransaction extends Model
{
    public static function booted(): void
    {
        static::updating(function (BankTransaction $model): void {
            if (! (bool) $model->getOriginal('is_matched')) {
                return;
            }
            $dirty = array_keys($model->getDirty());
            $allowed = ['updated_at'];
            $illegal = array_diff($dirty, $allowed);
            if ($illegal !== []) {
                throw new \DomainException('Bank transaction is immutable after match.');
            }
        });
    }

    protected $fillable = [
        'import_batch_uuid',
        'transaction_date',
        'transaction_time',
        'amount',
        'currency',
        'sender_name',
        'bank_reference',
        'description',
        'reference_extracted',
        'is_matched',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
        'is_matched'       => 'boolean',
    ];

    public function reconciliationMatches(): HasMany
    {
        return $this->hasMany(ReconciliationMatch::class);
    }
}
