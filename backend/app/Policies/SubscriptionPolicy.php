<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->company_id === $subscription->company_id;
    }

    public function renew(User $user, Subscription $subscription): bool
    {
        return $user->company_id === $subscription->company_id
            && $user->role === 'owner';
    }

    public function manage(User $user): bool
    {
        return $user->role === 'owner';
    }
}
