<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    private function roleValue(User $user): string
    {
        $role = $user->role;

        if ($role instanceof \BackedEnum) {
            return (string) $role->value;
        }

        if ($role instanceof \UnitEnum) {
            return $role->name;
        }

        return (string) $role;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id;
    }

    public function create(User $user): bool
    {
        return $this->roleValue($user) === 'owner';
    }

    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && in_array($this->roleValue($user), ['owner', 'manager'], true);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && $this->roleValue($user) === 'owner';
    }
}
