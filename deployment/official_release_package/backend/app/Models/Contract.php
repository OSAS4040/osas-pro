<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'title', 'party_name', 'party_type',
        'party_email', 'party_phone', 'party_cr', 'party_tax_number',
        'description', 'value', 'currency', 'payment_policy',
        'payment_day', 'payment_terms', 'start_date', 'end_date',
        'alert_days_before', 'status', 'signed_at', 'document_url',
        'signed_document_url', 'created_by', 'metadata',
    ];

    protected $casts = [
        'payment_terms' => 'array',
        'metadata'      => 'array',
        'start_date'    => 'date',
        'end_date'      => 'date',
        'value'         => 'decimal:2',
    ];

    public function company()       { return $this->belongsTo(Company::class); }
    public function creator()       { return $this->belongsTo(User::class, 'created_by'); }
    public function notifications() { return $this->hasMany(\App\Models\ContractNotification::class); }

    public function serviceItems()
    {
        return $this->hasMany(ContractServiceItem::class, 'contract_id');
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return (int) now()->diffInDays($this->end_date, false);
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->days_until_expiry <= $this->alert_days_before && $this->days_until_expiry >= 0;
    }
}
