<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * High-level classification for post-auth routing (WAVE 1 / PR2).
 * Derived from user.role, company_id, customer_id, and platform config — not from UI strings.
 */
enum LoginPrincipalKind: string
{
    case PlatformEmployee = 'platform_employee';

    case TenantUser = 'tenant_user';

    case CustomerUser = 'customer_user';

    case Unknown = 'unknown';
}
