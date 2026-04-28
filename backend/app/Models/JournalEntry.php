<?php

namespace App\Models;

use App\Enums\JournalEntryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'branch_id',
        'entry_number',
        'type',
        'source_type',
        'source_id',
        'reversed_by_entry_id',
        'reversed_entry_id',
        'entry_date',
        'description',
        'total_debit',
        'total_credit',
        'currency',
        'trace_id',
        'posting_idempotency_key',
        'created_by_user_id',
    ];

    protected $casts = [
        'type'       => JournalEntryType::class,
        'entry_date' => 'date',
        'total_debit'  => 'float',
        'total_credit' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_entry_id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_entry_id');
    }

    public function isBalanced(): bool
    {
        return abs((float)$this->total_debit - (float)$this->total_credit) < 0.001;
    }
}
