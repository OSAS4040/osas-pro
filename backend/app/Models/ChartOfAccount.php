<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';

    protected static function booted(): void
    {
        static::updating(function (ChartOfAccount $account) {
            if ($account->is_system && $account->isDirty(['code', 'type'])) {
                throw new \RuntimeException('System chart accounts cannot change code or type.');
            }
        });

        static::deleting(function (ChartOfAccount $account) {
            if ($account->is_system) {
                throw new \RuntimeException('System chart accounts cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'name_ar',
        'type',
        'sub_type',
        'parent_id',
        'is_active',
        'is_system',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'type'      => AccountType::class,
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function getBalanceAttribute(): float
    {
        $debits  = $this->journalLines()->where('type', 'debit')->sum('amount');
        $credits = $this->journalLines()->where('type', 'credit')->sum('amount');

        return $this->type->normalBalance() === 'debit'
            ? (float)$debits - (float)$credits
            : (float)$credits - (float)$debits;
    }
}
