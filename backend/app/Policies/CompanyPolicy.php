<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
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
        return $user->role === 'owner';
    }

    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && in_array($user->role, ['owner', 'manager']);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->company_id === $company->id
            && $user->role === 'owner';
    }
}
