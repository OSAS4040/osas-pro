<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\LoginEligibilityResult;

/**
 * Central gate: may this user receive a session / API token after credentials or OTP are valid?
 *
 * Order: security-relevant status first, then lifecycle, then disabled flag on otherwise-active accounts.
 * Does not evaluate company/branch/subscription (see AuthController::loginPreTokenGuards).
 */
final class ResolveLoginEligibilityAction
{
    public function __invoke(?User $user): LoginEligibilityResult
    {
        if ($user === null) {
            return LoginEligibilityResult::denied(
                LoginEligibilityResult::REASON_ACCOUNT_NOT_FOUND,
                'auth.login.account_not_found',
                '',
                false,
            );
        }

        $statusRaw = (string) $user->getRawOriginal('status');
        $status = UserStatus::tryFrom($statusRaw);
        $isActive = (bool) $user->is_active;

        if ($status === UserStatus::Blocked) {
            return LoginEligibilityResult::denied(
                LoginEligibilityResult::REASON_ACCOUNT_BLOCKED,
                'auth.login.account_blocked',
                $statusRaw,
                $isActive,
            );
        }

        if ($status === UserStatus::Suspended) {
            return LoginEligibilityResult::denied(
                LoginEligibilityResult::REASON_ACCOUNT_SUSPENDED,
                'auth.login.account_suspended',
                $statusRaw,
                $isActive,
            );
        }

        if ($status === UserStatus::Inactive) {
            return LoginEligibilityResult::denied(
                LoginEligibilityResult::REASON_ACCOUNT_INACTIVE,
                'auth.login.account_inactive',
                $statusRaw,
                $isActive,
            );
        }

        if ($status === null || $status !== UserStatus::Active) {
            return LoginEligibilityResult::denied(
                LoginEligibilityResult::REASON_LOGIN_NOT_ALLOWED,
                'auth.login.not_allowed',
                $statusRaw,
                $isActive,
            );
        }

        if (! $isActive) {
            return LoginEligibilityResult::denied(
                LoginEligibilityResult::REASON_ACCOUNT_DISABLED,
                'auth.login.account_disabled',
                $statusRaw,
                false,
            );
        }

        return LoginEligibilityResult::allowed($statusRaw, true);
    }
}
