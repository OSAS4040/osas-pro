<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Enums\LoginGuardHint;
use App\Enums\LoginPrincipalKind;

/**
 * Stable contract for clients: prefer {@see self::principalKind} and route hints over raw message text.
 */
final class LoginAccountContext
{
    /**
     * @param  array<string, mixed>  $displayContext
     */
    public function __construct(
        public readonly LoginPrincipalKind $principalKind,
        public readonly int $userId,
        public readonly ?int $companyId,
        public readonly ?int $customerId,
        public readonly string $homeRouteHint,
        public readonly LoginGuardHint $guardHint,
        public readonly string $role,
        public readonly bool $requiresContextSelection,
        public readonly array $displayContext = [],
        public readonly ?string $platformRole = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'principal_kind'               => $this->principalKind->value,
            'user_id'                      => $this->userId,
            'company_id'                   => $this->companyId,
            'customer_id'                  => $this->customerId,
            'home_route_hint'              => $this->homeRouteHint,
            'guard_hint'                   => $this->guardHint->value,
            'role'                         => $this->role,
            'requires_context_selection'   => $this->requiresContextSelection,
            'display_context'              => $this->displayContext,
            'platform_role'                => $this->platformRole,
        ];
    }
}
