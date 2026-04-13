<?php

declare(strict_types=1);

return [
    /**
     * When false, platform admin API routes return 404 (hide surface for tenants / scanners).
     * Does not affect tenant SaaS routes under company context.
     */
    'admin_enabled' => env('PLATFORM_ADMIN_ENABLED', true),

    /**
     * IAM role applied at login when the account is allowlisted but platform_role is null.
     */
    'default_role_for_whitelist' => env('PLATFORM_DEFAULT_WHITELIST_ROLE', 'platform_admin'),
];
