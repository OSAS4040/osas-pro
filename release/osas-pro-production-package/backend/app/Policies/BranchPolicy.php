<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
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

    public function view(User $user, Branch $branch): bool
    {
        return $user->company_id === $branch->company_id
            && ($user->branch_id === null
                || $user->branch_id === $branch->id
                || $branch->cross_branch_access);
    }

    public function create(User $user): bool
    {
        return in_array($this->roleValue($user), ['owner', 'manager'], true);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->company_id === $branch->company_id
            && in_array($this->roleValue($user), ['owner', 'manager'], true);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->company_id === $branch->company_id
            && $this->roleValue($user) === 'owner'
            && ! $branch->is_main;
    }
}
