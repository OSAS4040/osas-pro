<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function view(User $user, User $target): bool
    {
        return $user->company_id === $target->company_id
            && in_array($user->role, ['owner', 'manager']);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager']);
    }

    public function update(User $user, User $target): bool
    {
        if ($user->company_id !== $target->company_id) {
            return false;
        }

        if ($user->id === $target->id) {
            return true;
        }

        if ($target->role === 'owner' && $user->role !== 'owner') {
            return false;
        }

        return in_array($user->role, ['owner', 'manager']);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->company_id === $target->company_id
            && $user->role === 'owner'
            && $user->id !== $target->id;
    }

    public function impersonate(User $user, User $target): bool
    {
        return $user->role === 'owner'
            && $user->company_id === $target->company_id
            && $user->id !== $target->id;
    }
}
