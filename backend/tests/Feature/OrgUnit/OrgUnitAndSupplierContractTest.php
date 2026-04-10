<?php

namespace Tests\Feature\OrgUnit;

use App\Models\OrgUnit;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrgUnitAndSupplierContractTest extends TestCase
{
    public function test_owner_can_create_and_tree_org_units(): void
    {
        $t = $this->createTenant('owner');

        $r1 = $this->actingAsUser($t['user'])->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Fleet Ops',
        ]);
        $r1->assertStatus(201);
        $sectorId = $r1->json('data.id');

        $r2 = $this->actingAsUser($t['user'])->postJson('/api/v1/org-units', [
            'parent_id' => $sectorId,
            'type'      => OrgUnit::TYPE_DEPARTMENT,
            'name'      => 'Maintenance',
        ]);
        $r2->assertStatus(201);

        $tree = $this->actingAsUser($t['user'])->getJson('/api/v1/org-units/tree');
        $tree->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($tree->json('data')));
    }

    public function test_manager_can_assign_user_org_unit(): void
    {
        $t = $this->createTenant('manager');

        $sector = OrgUnit::create([
            'uuid'       => \Illuminate\Support\Str::uuid()->toString(),
            'company_id' => $t['company']->id,
            'parent_id'  => null,
            'type'       => OrgUnit::TYPE_SECTOR,
            'name'       => 'S1',
        ]);

        $staff = $this->createUser($t['company'], $t['branch'], 'staff');

        $u = $this->actingAsUser($t['user'])->putJson('/api/v1/users/'.$staff->id, [
            'org_unit_id' => $sector->id,
        ]);
        $u->assertStatus(200);
        $this->assertEquals($sector->id, $u->json('data.org_unit_id'));
    }

    public function test_supplier_contract_upload_list_delete(): void
    {
        Storage::fake('local');
        $t = $this->createTenant('owner');

        $supplier = Supplier::create([
            'uuid'                => \Illuminate\Support\Str::uuid()->toString(),
            'company_id'          => $t['company']->id,
            'created_by_user_id'  => $t['user']->id,
            'name'                => 'Vendor A',
            'is_active'           => true,
            'status'              => 'active',
            'version'             => 1,
        ]);

        $pdf = UploadedFile::fake()->create('contract.pdf', 200, 'application/pdf');

        $up = $this->actingAsUser($t['user'])->post('/api/v1/suppliers/'.$supplier->id.'/contracts', [
            'title'      => 'Annual',
            'expires_at' => now()->addMonth()->toDateString(),
            'file'       => $pdf,
        ]);
        $up->assertStatus(201);
        $cid = $up->json('data.id');
        Storage::disk('local')->assertExists($up->json('data.stored_path'));

        $list = $this->actingAsUser($t['user'])->getJson('/api/v1/suppliers/'.$supplier->id.'/contracts');
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data'));

        $del = $this->actingAsUser($t['user'])->deleteJson('/api/v1/suppliers/'.$supplier->id.'/contracts/'.$cid);
        $del->assertStatus(200);
        $this->assertDatabaseMissing('supplier_contracts', ['id' => $cid]);
    }

    public function test_retail_tenant_org_units_forbidden_until_feature_enabled(): void
    {
        $t = $this->createTenant('owner');
        $t['company']->update([
            'settings' => [
                'business_profile' => [
                    'business_type'   => 'retail',
                    'feature_matrix'  => [],
                ],
            ],
        ]);

        $blocked = $this->actingAsUser($t['user'])->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Blocked',
        ]);
        $blocked->assertStatus(403);
        $blocked->assertJsonPath('code', 'business_feature_disabled');
        $blocked->assertJsonPath('feature', 'org_structure');

        $t['company']->update([
            'settings' => [
                'business_profile' => [
                    'business_type'  => 'retail',
                    'feature_matrix' => ['org_structure' => true],
                ],
            ],
        ]);

        $ok = $this->actingAsUser($t['user'])->postJson('/api/v1/org-units', [
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Allowed',
        ]);
        $ok->assertStatus(201);
    }

    public function test_retail_tenant_supplier_contracts_forbidden_until_feature_enabled(): void
    {
        Storage::fake('local');
        $t = $this->createTenant('owner');
        $t['company']->update([
            'settings' => [
                'business_profile' => [
                    'business_type'  => 'retail',
                    'feature_matrix' => [],
                ],
            ],
        ]);

        $supplier = Supplier::create([
            'uuid'               => \Illuminate\Support\Str::uuid()->toString(),
            'company_id'         => $t['company']->id,
            'created_by_user_id' => $t['user']->id,
            'name'               => 'Vendor B',
            'is_active'          => true,
            'status'             => 'active',
            'version'            => 1,
        ]);

        $pdf = UploadedFile::fake()->create('c.pdf', 200, 'application/pdf');

        $blocked = $this->actingAsUser($t['user'])->post('/api/v1/suppliers/'.$supplier->id.'/contracts', [
            'title'      => 'X',
            'expires_at' => now()->addMonth()->toDateString(),
            'file'       => $pdf,
        ]);
        $blocked->assertStatus(403);
        $blocked->assertJsonPath('code', 'business_feature_disabled');
        $blocked->assertJsonPath('feature', 'supplier_contract_mgmt');

        $t['company']->update([
            'settings' => [
                'business_profile' => [
                    'business_type'  => 'retail',
                    'feature_matrix' => ['supplier_contract_mgmt' => true],
                ],
            ],
        ]);

        $pdf2 = UploadedFile::fake()->create('c2.pdf', 200, 'application/pdf');
        $ok = $this->actingAsUser($t['user'])->post('/api/v1/suppliers/'.$supplier->id.'/contracts', [
            'title'      => 'Y',
            'expires_at' => now()->addMonth()->toDateString(),
            'file'       => $pdf2,
        ]);
        $ok->assertStatus(201);
    }

    public function test_org_unit_id_on_user_rejected_when_org_structure_disabled(): void
    {
        $t = $this->createTenant('owner');

        $sector = OrgUnit::create([
            'uuid'       => \Illuminate\Support\Str::uuid()->toString(),
            'company_id' => $t['company']->id,
            'parent_id'  => null,
            'type'       => OrgUnit::TYPE_SECTOR,
            'name'       => 'Legacy Sector',
        ]);

        $t['company']->update([
            'settings' => [
                'business_profile' => [
                    'business_type'  => 'service_center',
                    'feature_matrix' => ['org_structure' => false],
                ],
            ],
        ]);

        $staff = $this->createUser($t['company'], $t['branch'], 'staff');

        $blocked = $this->actingAsUser($t['user'])->putJson('/api/v1/users/'.$staff->id, [
            'org_unit_id' => $sector->id,
        ]);
        $blocked->assertStatus(422);

        $okClear = $this->actingAsUser($t['user'])->putJson('/api/v1/users/'.$staff->id, [
            'org_unit_id' => null,
        ]);
        $okClear->assertStatus(200);
    }
}
