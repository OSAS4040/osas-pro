<?php

declare(strict_types=1);

namespace Tests\Feature\ProductionReadiness;

use App\Enums\LoginPrincipalKind;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * AUTH gate — API login success + invalid rejection (automated contract).
 */
#[Group('production-readiness')]
final class ProductionReadinessAuthContractTest extends TestCase
{
    public function test_api_login_succeeds_with_valid_tenant_credentials(): void
    {
        $tenant = $this->createTenant('owner');

        $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'trace_id',
                'account_context' => ['principal_kind', 'guard_hint', 'home_route_hint', 'platform_role'],
            ])
            ->assertJsonPath('account_context.principal_kind', LoginPrincipalKind::TenantUser->value);
    }

    public function test_api_login_rejects_invalid_password(): void
    {
        $tenant = $this->createTenant('owner');

        $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'DefinitelyNotThePassword99!',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('reason_code', 'INVALID_CREDENTIALS');
    }
}
