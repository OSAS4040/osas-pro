<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OrgUnit;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * يؤكد أن حساب عميل في شركة لا يصل إلى بيانات شركة أخرى حتى مع معرفات URL متوقعة (IDOR).
 */
final class CustomerPortalCrossTenantIsolationTest extends TestCase
{
    private function reportsBase(): string
    {
        return '/api/v1/customer-portal/reports';
    }

    /**
     * @return array{tenantA: array<string, mixed>, tenantB: array<string, mixed>, custA: Customer, custB: Customer, userA: \App\Models\User, userB: \App\Models\User, staffA: \App\Models\User, staffB: \App\Models\User}
     */
    private function twoTenantPortalCustomers(): array
    {
        $tenantA = $this->createTenant('owner');
        $tenantB = $this->createTenant('owner');

        $custA = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenantA['company']->id,
            'branch_id' => $tenantA['branch']->id,
            'type' => 'b2b',
            'name' => 'Iso Tenant A',
            'email' => 'iso-a-'.Str::random(8).'@test.sa',
            'is_active' => true,
        ]);
        $custB = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenantB['company']->id,
            'branch_id' => $tenantB['branch']->id,
            'type' => 'b2b',
            'name' => 'Iso Tenant B',
            'email' => 'iso-b-'.Str::random(8).'@test.sa',
            'is_active' => true,
        ]);

        $userA = $this->createUser($tenantA['company'], $tenantA['branch'], 'customer', [
            'email' => $custA->email,
            'customer_id' => $custA->id,
        ]);
        $userB = $this->createUser($tenantB['company'], $tenantB['branch'], 'customer', [
            'email' => $custB->email,
            'customer_id' => $custB->id,
        ]);

        return [
            'tenantA' => $tenantA,
            'tenantB' => $tenantB,
            'custA' => $custA,
            'custB' => $custB,
            'userA' => $userA,
            'userB' => $userB,
            'staffA' => $tenantA['user'],
            'staffB' => $tenantB['user'],
        ];
    }

    public function test_completed_work_orders_list_never_includes_other_tenant(): void
    {
        $x = $this->twoTenantPortalCustomers();
        $tA = $x['tenantA'];
        $tB = $x['tenantB'];

        $vA = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tA['company']->id,
            'branch_id' => $tA['branch']->id,
            'customer_id' => $x['custA']->id,
            'created_by_user_id' => $x['staffA']->id,
            'plate_number' => 'ISO-A-'.Str::upper(Str::random(3)),
            'make' => 'A',
            'model' => '1',
            'year' => 2023,
            'is_active' => true,
        ]);
        $vB = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tB['company']->id,
            'branch_id' => $tB['branch']->id,
            'customer_id' => $x['custB']->id,
            'created_by_user_id' => $x['staffB']->id,
            'plate_number' => 'ISO-B-'.Str::upper(Str::random(3)),
            'make' => 'B',
            'model' => '2',
            'year' => 2022,
            'is_active' => true,
        ]);

        $at = now()->subDays(1);
        $woA = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tA['company']->id,
            'branch_id' => $tA['branch']->id,
            'customer_id' => $x['custA']->id,
            'vehicle_id' => $vA->id,
            'created_by_user_id' => $x['staffA']->id,
            'order_number' => 'WO-ISO-A-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 50,
            'actual_total' => 50,
            'version' => 0,
            'completed_at' => $at,
        ]);
        $woB = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tB['company']->id,
            'branch_id' => $tB['branch']->id,
            'customer_id' => $x['custB']->id,
            'vehicle_id' => $vB->id,
            'created_by_user_id' => $x['staffB']->id,
            'order_number' => 'WO-ISO-B-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 9000,
            'actual_total' => 9000,
            'version' => 0,
            'completed_at' => $at,
        ]);

        $from = $at->copy()->subDay()->toDateString();
        $to = $at->copy()->addDay()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to]);

        $resA = $this->actingAsUser($x['userA'])->getJson($this->reportsBase().'/work-orders-completed?'.$q);
        $resA->assertOk();
        $idsA = array_column($resA->json('data.rows') ?? [], 'id');
        $this->assertContains($woA->id, $idsA);
        $this->assertNotContains($woB->id, $idsA);

        $resB = $this->actingAsUser($x['userB'])->getJson($this->reportsBase().'/work-orders-completed?'.$q);
        $resB->assertOk();
        $idsB = array_column($resB->json('data.rows') ?? [], 'id');
        $this->assertContains($woB->id, $idsB);
        $this->assertNotContains($woA->id, $idsB);
    }

    public function test_invoices_report_never_includes_other_tenant_customer(): void
    {
        $x = $this->twoTenantPortalCustomers();
        $tA = $x['tenantA'];
        $tB = $x['tenantB'];
        $issued = now()->subDays(2);

        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tA['company']->id,
            'branch_id' => $tA['branch']->id,
            'created_by_user_id' => $x['staffA']->id,
            'customer_id' => $x['custA']->id,
            'invoice_number' => 'INV-ISO-A-'.Str::upper(Str::random(4)),
            'invoice_hash' => hash('sha256', Str::random(16)),
            'invoice_counter' => 1,
            'source_type' => 'pos',
            'source_id' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => 'pending',
            'currency' => 'SAR',
            'issued_at' => $issued,
        ]);

        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tB['company']->id,
            'branch_id' => $tB['branch']->id,
            'created_by_user_id' => $x['staffB']->id,
            'customer_id' => $x['custB']->id,
            'invoice_number' => 'INV-ISO-B-'.Str::upper(Str::random(4)),
            'invoice_hash' => hash('sha256', Str::random(16)),
            'invoice_counter' => 1,
            'source_type' => 'pos',
            'source_id' => 0,
            'subtotal' => 99999,
            'tax_amount' => 0,
            'total' => 99999,
            'paid_amount' => 0,
            'due_amount' => 99999,
            'status' => 'pending',
            'currency' => 'SAR',
            'issued_at' => $issued,
        ]);

        $from = $issued->copy()->subDay()->toDateString();
        $to = $issued->copy()->addDay()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to]);

        $resA = $this->actingAsUser($x['userA'])->getJson($this->reportsBase().'/invoices?'.$q);
        $resA->assertOk();
        foreach ($resA->json('data.rows') ?? [] as $row) {
            $this->assertStringNotContainsString('99999', json_encode($row, JSON_THROW_ON_ERROR));
        }
        $this->assertSame(1, (int) ($resA->json('meta.total') ?? 0));

        $this->actingAsUser($x['userB'])
            ->getJson($this->reportsBase().'/invoices?'.$q)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_org_unit_update_for_foreign_id_returns_404(): void
    {
        $x = $this->twoTenantPortalCustomers();
        $unitB = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $x['tenantB']['company']->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Sector OtherCo',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAsUser($x['userA'])
            ->putJson('/api/v1/customer-portal/org-units/'.$unitB->id, [
                'name' => 'Hacked Name',
            ])
            ->assertNotFound();
    }

    public function test_team_user_update_for_foreign_id_returns_404(): void
    {
        $x = $this->twoTenantPortalCustomers();

        $this->actingAsUser($x['userA'])
            ->putJson('/api/v1/customer-portal/team-users/'.$x['userB']->id, [
                'name' => 'Should Not Apply',
            ])
            ->assertNotFound();
    }

    public function test_org_units_tree_lists_only_own_company_nodes(): void
    {
        $x = $this->twoTenantPortalCustomers();

        $unitA = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $x['tenantA']['company']->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Sector CoA',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $unitB = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $x['tenantB']['company']->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Sector CoB',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $treeA = $this->actingAsUser($x['userA'])->getJson('/api/v1/customer-portal/org-units/tree');
        $treeA->assertOk();
        $idsA = $this->collectTreeOrgUnitIds($treeA->json('data') ?? []);
        $this->assertContains($unitA->id, $idsA);
        $this->assertNotContains($unitB->id, $idsA);

        $treeB = $this->actingAsUser($x['userB'])->getJson('/api/v1/customer-portal/org-units/tree');
        $treeB->assertOk();
        $idsB = $this->collectTreeOrgUnitIds($treeB->json('data') ?? []);
        $this->assertContains($unitB->id, $idsB);
        $this->assertNotContains($unitA->id, $idsB);
    }

    public function test_org_unit_breakdown_rows_only_include_own_company_org_units(): void
    {
        $x = $this->twoTenantPortalCustomers();

        $unitA = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $x['tenantA']['company']->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Breakdown A',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $x['tenantB']['company']->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Breakdown B',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $from = now()->subDays(30)->toDateString();
        $to = now()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to]);

        $res = $this->actingAsUser($x['userA'])->getJson($this->reportsBase().'/org-unit-breakdown?'.$q);
        $res->assertOk();
        foreach ($res->json('data.rows') ?? [] as $row) {
            $cid = OrgUnit::withoutGlobalScopes()->whereKey((int) $row['org_unit_id'])->value('company_id');
            $this->assertSame($x['tenantA']['company']->id, (int) $cid);
        }
        $names = array_merge(
            array_column($res->json('data.rows') ?? [], 'name'),
            array_column($res->json('data.rows') ?? [], 'name_ar'),
        );
        $this->assertContains('Breakdown A', $names);
        $this->assertNotContains('Breakdown B', $names);
    }

    public function test_foreign_org_unit_query_param_does_not_enable_cross_tenant_scope(): void
    {
        $x = $this->twoTenantPortalCustomers();
        $tA = $x['tenantA'];
        $tB = $x['tenantB'];

        $sectorB = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tB['company']->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'Foreign Root',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $vA = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tA['company']->id,
            'branch_id' => $tA['branch']->id,
            'customer_id' => $x['custA']->id,
            'created_by_user_id' => $x['staffA']->id,
            'plate_number' => 'FX-'.Str::upper(Str::random(3)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
            'is_active' => true,
        ]);

        $at = now()->subDays(1);
        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tA['company']->id,
            'branch_id' => $tA['branch']->id,
            'customer_id' => $x['custA']->id,
            'vehicle_id' => $vA->id,
            'created_by_user_id' => $x['staffA']->id,
            'order_number' => 'WO-FX-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 10,
            'actual_total' => 10,
            'version' => 0,
            'completed_at' => $at,
        ]);

        $from = $at->copy()->subDay()->toDateString();
        $to = $at->copy()->addDay()->toDateString();
        $q = http_build_query([
            'from' => $from,
            'to' => $to,
            'org_unit_id' => $sectorB->id,
        ]);

        $res = $this->actingAsUser($x['userA'])->getJson($this->reportsBase().'/work-orders-completed?'.$q);
        $res->assertOk();
        // وحدة شركة ب لا تُحمَّل لشركة أ → نطاق الوحدة فارغ؛ لا يُطبَّق فلتر org؛ الأرام لا تزال مقيدة بـ customer_id للعميل أ.
        $this->assertGreaterThanOrEqual(1, count($res->json('data.rows') ?? []));
        $this->assertStringStartsWith('WO-FX-', (string) $res->json('data.rows.0.order_number'));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<int>
     */
    private function collectTreeOrgUnitIds(array $nodes): array
    {
        $ids = [];
        foreach ($nodes as $node) {
            if (isset($node['id'])) {
                $ids[] = (int) $node['id'];
            }
            if (! empty($node['children']) && is_array($node['children'])) {
                $ids = array_merge($ids, $this->collectTreeOrgUnitIds($node['children']));
            }
        }

        return $ids;
    }
}
