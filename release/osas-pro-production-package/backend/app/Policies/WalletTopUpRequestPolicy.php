<?php

namespace App\Policies;

use App\Enums\WalletTopUpRequestStatus;
use App\Models\User;
use App\Models\WalletTopUpRequest;

class WalletTopUpRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('wallet.top_up_requests.create');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('wallet.top_up_requests.view')
            || $user->hasPermission('wallet.top_up_requests.review');
    }

    public function view(User $user, WalletTopUpRequest $request): bool
    {
        if ($user->company_id !== $request->company_id) {
            return false;
        }
        if ($user->hasPermission('wallet.top_up_requests.review')) {
            return true;
        }

        return $user->hasPermission('wallet.top_up_requests.view')
            && (int) $request->requested_by === (int) $user->id;
    }

    public function updateReturned(User $user, WalletTopUpRequest $request): bool
    {
        return $user->company_id === $request->company_id
            && $user->hasPermission('wallet.top_up_requests.create')
            && (int) $request->requested_by === (int) $user->id
            && $request->status === WalletTopUpRequestStatus::ReturnedForRevision;
    }

    public function resubmit(User $user, WalletTopUpRequest $request): bool
    {
        return $this->updateReturned($user, $request);
    }

    public function review(User $user, WalletTopUpRequest $request): bool
    {
        return $user->company_id === $request->company_id
            && $user->hasPermission('wallet.top_up_requests.review');
    }

    public function downloadReceipt(User $user, WalletTopUpRequest $request): bool
    {
        return $this->view($user, $request);
    }
}
