<?php

namespace Tests\Feature\Users;

use App\Models\OrgUnit;
use App\Models\User;
use Tests\TestCase;

class TeamUsersApiTest extends TestCase
{
    public function test_manager_lists_users_with_org_unit_relation(): void
    {
        $t = $this->createTenant('manager');

        $sector = OrgUnit::create([
            'uuid'       => \Illuminate\Support\Str::uuid()->toString(),
            'company_id' => $t['company']->id,
            'parent_id'  => null,
            'type'       => OrgUnit::TYPE_SECTOR,
            'name'       => 'Ops',
        ]);

        $staff = $this->createUser($t['company'], $t['branch'], 'staff', ['org_unit_id' => $sector->id]);

        $res = $this->actingAsUser($t['user'])->getJson('/api/v1/users');
        $res->assertStatus(200);
        $payload = $res->json('data.data');
        $this->assertIsArray($payload);
        $row = collect($payload)->firstWhere('id', $staff->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('org_unit', $row);
        $this->assertEquals('Ops', $row['org_unit']['name'] ?? null);
    }

    public function test_search_filters_by_name_safe_like(): void
    {
        $t = $this->createTenant('owner');

        User::query()->where('company_id', $t['company']->id)->where('id', '!=', $t['user']->id)->delete();

        $this->createUser($t['company'], $t['branch'], 'staff', [
            'name'  => 'UniqueSearchNameX',
            'email' => 'unique_x_'.uniqid().'@test.sa',
        ]);

        $hit = $this->actingAsUser($t['user'])->getJson('/api/v1/users?search='.urlencode('UniqueSearchNameX'));
        $hit->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($hit->json('data.data')));

        $miss = $this->actingAsUser($t['user'])->getJson('/api/v1/users?search='.urlencode('NoSuchUserzzz'));
        $miss->assertStatus(200);
        $this->assertCount(0, $miss->json('data.data'));
    }

    public function test_invalid_role_query_returns_422(): void
    {
        $t = $this->createTenant('owner');

        $r = $this->actingAsUser($t['user'])->getJson('/api/v1/users?role=not_a_real_role');
        $r->assertStatus(422);
    }

    public function test_create_user_response_includes_org_unit_when_set(): void
    {
        $t = $this->createTenant('owner');

        $sector = OrgUnit::create([
            'uuid'       => \Illuminate\Support\Str::uuid()->toString(),
            'company_id' => $t['company']->id,
            'parent_id'  => null,
            'type'       => OrgUnit::TYPE_SECTOR,
            'name'       => 'Fleet',
        ]);

        $email = 'new_staff_'.uniqid().'@test.sa';

        $res = $this->actingAsUser($t['user'])->postJson('/api/v1/users', [
            'name'        => 'New Staff',
            'email'       => $email,
            'password'    => 'Password123!',
            'role'        => 'staff',
            'branch_id'   => $t['branch']->id,
            'org_unit_id' => $sector->id,
            'is_active'   => true,
        ]);

        $res->assertStatus(201);
        $this->assertEquals($sector->id, $res->json('data.org_unit_id'));
        $this->assertEquals('Fleet', $res->json('data.org_unit.name'));
    }
}
