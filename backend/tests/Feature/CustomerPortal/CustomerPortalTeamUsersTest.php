<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Models\User;
use Tests\TestCase;

final class CustomerPortalTeamUsersTest extends TestCase
{
    public function test_customer_can_crud_team_user_inside_own_company(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $customerUser = $this->createUser($company, $branch, 'customer');

        $createRes = $this->actingAsUser($customerUser)->postJson('/api/v1/customer-portal/team-users', [
            'name' => 'Portal Viewer',
            'email' => 'portal-viewer-'.uniqid().'@test.sa',
            'password' => 'Password123!',
            'role' => 'viewer',
            'is_active' => true,
            'send_welcome_notification' => false,
        ]);
        $createRes->assertCreated();
        $createdId = (int) $createRes->json('data.id');
        $this->assertGreaterThan(0, $createdId);
        $this->assertSame($branch->id, (int) $createRes->json('data.branch_id'));
        $this->assertArrayNotHasKey('is_platform_user', (array) $createRes->json('data'));
        $this->assertArrayNotHasKey('platform_role', (array) $createRes->json('data'));

        $listRes = $this->actingAsUser($customerUser)->getJson('/api/v1/customer-portal/team-users?per_page=50');
        $listRes->assertOk();
        $listedIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $listRes->json('data.data') ?? []);
        $this->assertContains($createdId, $listedIds);
        $this->assertArrayNotHasKey('is_platform_user', (array) ($listRes->json('data.data.0') ?? []));
        $this->assertArrayNotHasKey('platform_role', (array) ($listRes->json('data.data.0') ?? []));

        $this->actingAsUser($customerUser)->putJson('/api/v1/customer-portal/team-users/'.$createdId, [
            'name' => 'Portal Viewer Updated',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.name', 'Portal Viewer Updated')
            ->assertJsonPath('data.is_active', false);

        $this->actingAsUser($customerUser)->deleteJson('/api/v1/customer-portal/team-users/'.$createdId)
            ->assertOk();
        $this->assertSoftDeleted('users', ['id' => $createdId, 'company_id' => $company->id]);
    }

    public function test_customer_cannot_delete_current_user(): void
    {
        $tenant = $this->createTenant('owner');
        $customerUser = $this->createUser($tenant['company'], $tenant['branch'], 'customer');

        $this->actingAsUser($customerUser)
            ->deleteJson('/api/v1/customer-portal/team-users/'.$customerUser->id)
            ->assertStatus(422);
    }

    public function test_staff_cannot_access_customer_team_users_endpoints(): void
    {
        $tenant = $this->createTenant('owner');
        $staff = $tenant['user'];

        $this->actingAsUser($staff)
            ->getJson('/api/v1/customer-portal/team-users')
            ->assertForbidden();
    }

    public function test_customer_hits_subscription_quota_when_creating_user(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $customerUser = $this->createUser($company, $branch, 'customer');

        $sub = $tenant['subscription'];
        $sub->max_users = User::query()->where('company_id', $company->id)->count();
        $sub->save();

        $this->actingAsUser($customerUser)->postJson('/api/v1/customer-portal/team-users', [
            'name' => 'Blocked User',
            'email' => 'blocked-'.uniqid().'@test.sa',
            'password' => 'Password123!',
            'role' => 'viewer',
            'send_welcome_notification' => false,
        ])->assertStatus(422);
    }
}
