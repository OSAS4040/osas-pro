<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasTenantScope, HasRoles;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user->company_id) {
                return;
            }

            $company = Company::withTrashed()->find($user->company_id);
            if (! $company) {
                throw new \RuntimeException(
                    'Tenant integrity: users.company_id must reference an existing companies row.'
                );
            }
            if ($company->trashed()) {
                throw new \RuntimeException(
                    'Tenant integrity: cannot save user against a soft-deleted company.'
                );
            }

            if ($user->branch_id === null) {
                return;
            }

            $branchOk = Branch::query()
                ->withoutGlobalScope('tenant')
                ->where('id', $user->branch_id)
                ->where('company_id', $user->company_id)
                ->whereNull('deleted_at')
                ->exists();

            if (! $branchOk) {
                throw new \RuntimeException(
                    'Tenant integrity: users.branch_id must reference a non-deleted branch belonging to users.company_id.'
                );
            }

            if ($user->org_unit_id === null) {
                return;
            }

            $orgOk = OrgUnit::query()
                ->withoutGlobalScope('tenant')
                ->where('id', $user->org_unit_id)
                ->where('company_id', $user->company_id)
                ->exists();

            if (! $orgOk) {
                throw new \RuntimeException(
                    'Tenant integrity: users.org_unit_id must reference an org unit belonging to users.company_id.'
                );
            }
        });
    }

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'org_unit_id', 'customer_id',
        'name', 'email', 'password', 'phone', 'role', 'status', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'status'            => UserStatus::class,
        'role'              => UserRole::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'org_unit_id');
    }

    public function isActive(): bool
    {
        return $this->is_active
            && $this->status === UserStatus::Active;
    }

    public function canLogin(): bool
    {
        return $this->is_active && $this->status === UserStatus::Active;
    }
}
