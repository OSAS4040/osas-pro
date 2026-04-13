<?php

namespace App\Http\Middleware;

/**
 * @deprecated Use {@see GlobalTenantGuardMiddleware} via the `global.tenant` alias.
 *             Retained as `tenant` alias for backward compatibility.
 */
class TenantScopeMiddleware extends GlobalTenantGuardMiddleware
{
}
