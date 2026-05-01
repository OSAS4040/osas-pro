<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * عقد الإطار بين المنصّة والمزوّد (شريك التنفيذ) — يُميّز عبر metadata عند إنشاء/ربط العقد من الإدارة.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function metadataMarksPlatformProviderAgreement(array $metadata): bool
    {
        if (($metadata['platform_provider_agreement'] ?? false) === true) {
            return true;
        }

        return ($metadata['agreement_scope'] ?? null) === 'platform_provider';
    }

    public function isPlatformProviderAgreement(): bool
    {
        $m = is_array($this->metadata) ? $this->metadata : [];

        return self::metadataMarksPlatformProviderAgreement($m);
    }

    /** @param  Builder<Contract>  $query */
    public function scopePlatformProviderAgreements(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('metadata->platform_provider_agreement', true)
                ->orWhere('metadata->agreement_scope', 'platform_provider');
        });
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
