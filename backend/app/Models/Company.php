<?php

namespace App\Models;

use App\Enums\CompanyFinancialModel;
use App\Enums\CompanyFinancialModelStatus;
use App\Enums\CompanyStatus;
use App\Services\SystemChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::created(function (Company $company) {
            SystemChartOfAccountsSeeder::ensureForCompany($company->id);
        });
    }

    protected $fillable = [
        'uuid', 'name', 'name_ar', 'tax_number', 'cr_number',
        'email', 'phone', 'address', 'city', 'country',
        'currency', 'timezone', 'logo_url', 'is_active', 'status', 'settings',
        'financial_model', 'financial_model_status', 'credit_limit',
        'platform_financial_reviewed_at', 'platform_financial_reviewed_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
        'status'    => CompanyStatus::class,
        'financial_model' => CompanyFinancialModel::class,
        'financial_model_status' => CompanyFinancialModelStatus::class,
        'credit_limit' => 'decimal:4',
        'platform_financial_reviewed_at' => 'datetime',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'grace_period'])
            ->latest();
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->status === CompanyStatus::Active;
    }
}
