<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->company_id === $branch->company_id
            && ($user->branch_id === null
                || $user->branch_id === $branch->id
                || $branch->cross_branch_access);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->company_id === $branch->company_id
            && in_array($user->role, ['owner', 'manager']);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->company_id === $branch->company_id
            && $user->role === 'owner'
            && ! $branch->is_main;
    }
}
