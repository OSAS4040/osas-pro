<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Actions\Auth\ResolveLoginContextAction;

/**
 * Result of {@see ResolveLoginContextAction}: eligibility (always) + account context when allowed.
 */
final class LoginContextResolution
{
    public function __construct(
        public readonly LoginEligibilityResult $eligibility,
        public readonly ?LoginAccountContext $accountContext,
    ) {}

    public function allowed(): bool
    {
        return $this->eligibility->allowed;
    }
}
