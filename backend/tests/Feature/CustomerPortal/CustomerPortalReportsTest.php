<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\InvoiceStatus;
use App\Enums\WorkOrderItemType;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OrgUnit;
use App\Models\Product;
use App\Models\Service;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerPortalReportsTest extends TestCase
{
    private function queryBase(): string
    {
        return '/api/v1/customer-portal/reports';
    }

    public function test_customer_receives_service_and_product_aggregates_for_completed_orders(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $staff = $tenant['user'];

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Report Customer',
            'email' => 'report-cust-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);

        $otherCustomer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Other Customer',
            'email' => 'other-cust-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);

        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
            'is_platform_user' => false,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'name' => 'Tire Rotation',
            'name_ar' => 'تدوير إطارات',
            'code' => 'SRV-'.Str::upper(Str::random(4)),
            'base_price' => 50,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        $unit = Unit::create([
            'company_id' => $company->id,
            'name' => 'Piece',
            'symbol' => 'pcs',
            'type' => 'quantity',
            'is_base' => true,
            'is_system' => false,
            'is_active' => true,
        ]);

        $product = Product::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'name' => 'Oil Filter',
            'sku' => 'FLT-'.Str::upper(Str::random(4)),
            'product_type' => 'physical',
            'unit_id' => $unit->id,
            'sale_price' => 25,
            'cost_price' => 10,
            'track_inventory' => false,
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'RPT-'.Str::upper(Str::random(3)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2022,
            'is_active' => true,
        ]);

        $completedAt = now()->subDays(2);
        $wo = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $staff->id,
            'order_number' => 'WO-RPT-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 100,
            'actual_total' => 100,
            'version' => 0,
            'completed_at' => $completedAt,
        ]);

        WorkOrderItem::create([
            'company_id' => $company->id,
            'work_order_id' => $wo->id,
            'product_id' => null,
            'service_id' => $service->id,
            'item_type' => WorkOrderItemType::Service,
            'name' => $service->name,
            'quantity' => 2,
            'unit_price' => 50,
            'discount_amount' => 0,
            'tax_rate' => 15,
            'tax_amount' => 15,
            'subtotal' => 100,
            'total' => 115,
        ]);

        WorkOrderItem::create([
            'company_id' => $company->id,
            'work_order_id' => $wo->id,
            'product_id' => $product->id,
            'service_id' => null,
            'item_type' => WorkOrderItemType::Part,
            'name' => $product->name,
            'sku' => $product->sku,
            'quantity' => 1,
            'unit_price' => 25,
            'discount_amount' => 0,
            'tax_rate' => 15,
            'tax_amount' => 3.75,
            'subtotal' => 25,
            'total' => 28.75,
        ]);

        $otherVehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $otherCustomer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'OTH-'.Str::upper(Str::random(3)),
            'make' => 'Z',
            'model' => 'W',
            'year' => 2021,
            'is_active' => true,
        ]);

        $woOther = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_id' => $otherVehicle->id,
            'created_by_user_id' => $staff->id,
            'order_number' => 'WO-OTH-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 999,
            'actual_total' => 999,
            'version' => 0,
            'completed_at' => $completedAt,
        ]);

        WorkOrderItem::create([
            'company_id' => $company->id,
            'work_order_id' => $woOther->id,
            'product_id' => null,
            'service_id' => $service->id,
            'item_type' => WorkOrderItemType::Service,
            'name' => $service->name,
            'quantity' => 99,
            'unit_price' => 10,
            'discount_amount' => 0,
            'tax_rate' => 15,
            'tax_amount' => 0,
            'subtotal' => 990,
            'total' => 990,
        ]);

        $from = $completedAt->copy()->subDay()->toDateString();
        $to = $completedAt->copy()->addDay()->toDateString();
        $q = ['from' => $from, 'to' => $to];

        $svcRes = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/work-order-items-by-service?'.http_build_query($q));
        $svcRes->assertOk();
        $svcRes->assertJsonPath('data.rows.0.id', $service->id);
        $this->assertStringContainsString('2.0000', (string) $svcRes->json('data.rows.0.total_quantity'));
        $svcRes->assertJsonPath('data.rows.0.total_amount', null);
        $svcRes->assertJsonPath('data.summary.total_amount', null);

        $prodRes = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/work-order-items-by-product?'.http_build_query($q));
        $prodRes->assertOk();
        $prodRes->assertJsonPath('data.rows.0.id', $product->id);

        $woRes = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/work-orders-completed?'.http_build_query($q));
        $woRes->assertOk();
        $woRes->assertJsonPath('data.rows.0.id', $wo->id);
        $woRes->assertJsonPath('data.rows.0.actual_total', null);
        $woRes->assertJsonPath('data.summary.total_actual', null);
        $this->assertSame(1, count($woRes->json('data.rows')));

        $optRes = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/filter-options?'.http_build_query($q));
        $optRes->assertOk();
        $this->assertGreaterThanOrEqual(1, count($optRes->json('data.services')));
        $this->assertGreaterThanOrEqual(1, count($optRes->json('data.products')));
    }

    public function test_staff_cannot_access_customer_portal_reports(): void
    {
        $tenant = $this->createTenant('owner');
        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();

        $this->actingAsUser($tenant['user'])
            ->getJson($this->queryBase().'/work-order-items-by-service?from='.$from.'&to='.$to)
            ->assertForbidden();
    }

    public function test_customer_can_filter_reports_by_org_unit_scope_and_list_tree(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];

        $sectorA = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'قطاع أ',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $divisionA = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'parent_id' => $sectorA->id,
            'type' => OrgUnit::TYPE_DIVISION,
            'name' => 'وحدة أ-1',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $sectorB = OrgUnit::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'parent_id' => null,
            'type' => OrgUnit::TYPE_SECTOR,
            'name' => 'قطاع ب',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $staffA = $this->createUser($company, $branch, 'staff', ['org_unit_id' => $divisionA->id]);
        $staffB = $this->createUser($company, $branch, 'staff', ['org_unit_id' => $sectorB->id]);

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Org Filter Customer',
            'email' => 'org-filter-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);
        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staffA->id,
            'plate_number' => 'ORG-'.Str::upper(Str::random(3)),
            'make' => 'M',
            'model' => 'N',
            'year' => 2024,
            'is_active' => true,
        ]);

        $completedAt = now()->subDay();
        $woA = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $staffA->id,
            'order_number' => 'WO-ORA-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 10,
            'actual_total' => 10,
            'version' => 0,
            'completed_at' => $completedAt,
        ]);
        $woB = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $staffB->id,
            'order_number' => 'WO-ORB-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 20,
            'actual_total' => 20,
            'version' => 0,
            'completed_at' => $completedAt,
        ]);

        $from = $completedAt->copy()->subDay()->toDateString();
        $to = $completedAt->copy()->addDay()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to, 'org_unit_id' => $sectorA->id]);

        $res = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/work-orders-completed?'.$q);
        $res->assertOk();
        $this->assertSame([$woA->id], array_column($res->json('data.rows'), 'id'));

        $treeRes = $this->actingAsUser($customerUser)->getJson('/api/v1/customer-portal/org-units/tree');
        $treeRes->assertOk();
        $this->assertSame($sectorA->id, $treeRes->json('data.0.id'));
    }

    public function test_customer_can_use_summary_and_invoices_reports(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Summary API Customer',
            'email' => 'sum-api-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);
        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        $from = now()->subDays(7)->toDateString();
        $to = now()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to]);

        $this->actingAsUser($customerUser)
            ->getJson($this->queryBase().'/summary?'.$q)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'invoices' => ['count', 'total_invoiced', 'total_paid', 'total_due'],
                    'work_orders' => ['active_open', 'completed_in_period', 'opened_in_period_by_status'],
                    'vehicles_registered',
                ],
            ]);

        $this->actingAsUser($customerUser)
            ->getJson($this->queryBase().'/invoices?'.$q)
            ->assertOk()
            ->assertJsonStructure(['data' => ['rows'], 'meta' => ['current_page', 'total']]);
    }

    public function test_internal_customer_user_can_view_financial_fields_in_reports(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $staff = $tenant['user'];

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Internal Financial Customer',
            'email' => 'int-fin-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);
        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
            'is_platform_user' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'FIN-'.Str::upper(Str::random(3)),
            'make' => 'N',
            'model' => 'M',
            'year' => 2024,
            'is_active' => true,
        ]);

        $issuedAt = now()->subDay();
        Invoice::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-FIN-'.Str::upper(Str::random(4)),
            'invoice_hash' => hash('sha256', Str::random(12)),
            'invoice_counter' => 1,
            'source_type' => 'pos',
            'source_id' => 1,
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 20,
            'due_amount' => 95,
            'status' => InvoiceStatus::Pending,
            'currency' => 'SAR',
            'vehicle_id' => $vehicle->id,
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->copy()->addDays(7),
        ]);

        WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $staff->id,
            'order_number' => 'WO-FIN-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 90,
            'actual_total' => 90,
            'version' => 0,
            'completed_at' => $issuedAt,
        ]);

        $from = $issuedAt->copy()->subDay()->toDateString();
        $to = $issuedAt->copy()->addDay()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to]);

        $summary = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/summary?'.$q);
        $summary->assertOk();
        $this->assertNotNull($summary->json('data.invoices.total_invoiced'));
        $this->assertNotNull($summary->json('data.work_orders.completed_amount_in_period'));

        $invoices = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/invoices?'.$q);
        $invoices->assertOk();
        $this->assertNotNull($invoices->json('data.rows.0.total'));
        $this->assertNotNull($invoices->json('data.rows.0.due_amount'));
    }

    public function test_reports_reject_date_range_longer_than_ninety_days(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Range Gate Customer',
            'email' => 'range-gate-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);
        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        $from = now()->subDays(100)->toDateString();
        $to = now()->toDateString();
        $q = http_build_query(['from' => $from, 'to' => $to]);

        $this->actingAsUser($customerUser)
            ->getJson($this->queryBase().'/summary?'.$q)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_completed_work_orders_report_excludes_soft_deleted_orders(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $staff = $tenant['user'];

        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Soft Delete Customer',
            'email' => 'soft-del-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);
        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'SD-'.Str::upper(Str::random(3)),
            'make' => 'S',
            'model' => 'D',
            'year' => 2024,
            'is_active' => true,
        ]);

        $completedAt = now()->subDay();
        $visible = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $staff->id,
            'order_number' => 'WO-SD-OK-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 40,
            'actual_total' => 40,
            'version' => 0,
            'completed_at' => $completedAt,
        ]);
        $deleted = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $staff->id,
            'order_number' => 'WO-SD-DEL-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 80,
            'actual_total' => 80,
            'version' => 0,
            'completed_at' => $completedAt,
        ]);
        $deleted->delete();

        $q = http_build_query([
            'from' => $completedAt->copy()->subDay()->toDateString(),
            'to' => $completedAt->copy()->addDay()->toDateString(),
        ]);

        $res = $this->actingAsUser($customerUser)->getJson($this->queryBase().'/work-orders-completed?'.$q);
        $res->assertOk();
        $ids = array_column($res->json('data.rows') ?? [], 'id');
        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($deleted->id, $ids);
    }
}
