<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['owner', 'manager']);
    }

    public function view(User $user, User $target): bool
    {
        return $user->company_id === $target->company_id
            && $user->hasRole(['owner', 'manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['owner', 'manager']);
    }

    public function update(User $user, User $target): bool
    {
        if ($user->company_id !== $target->company_id) {
            return false;
        }

        if ($user->id === $target->id) {
            return true;
        }

        if ($target->role === UserRole::Owner && $user->role !== UserRole::Owner) {
            return false;
        }

        return $user->hasRole(['owner', 'manager']);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->company_id === $target->company_id
            && $user->role === UserRole::Owner
            && $user->id !== $target->id;
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->role === UserRole::Owner
            && $user->company_id === $target->company_id
            && $user->id !== $target->id;
    }
}
