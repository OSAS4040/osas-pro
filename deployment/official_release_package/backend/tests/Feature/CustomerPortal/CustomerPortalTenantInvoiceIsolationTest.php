<?php

declare(strict_types=1);

namespace Tests\Feature\CustomerPortal;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerPortalTenantInvoiceIsolationTest extends TestCase
{
    private function ensurePermission(string $name): Permission
    {
        return Permission::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => 'sanctum'],
            ['group' => 'test', 'description' => $name],
        );
    }

    public function test_customer_portal_dashboard_counts_only_customer_facing_invoices(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $owner = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Portal Invoice Customer',
            'email' => 'portal-inv-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);

        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'provider_to_platform',
            'customer_visible' => false,
            'customer_type' => 'b2b',
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Provider line',
                'quantity' => 1,
                'unit_price' => 200,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'platform_to_customer',
            'customer_visible' => true,
            'customer_id' => $customer->id,
            'customer_type' => 'b2b',
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Customer line',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $dash = $this->actingAsUser($customerUser)->getJson('/api/v1/customer-portal/dashboard');
        $dash->assertOk();
        $this->assertSame(1, (int) $dash->json('data.stats.invoices'));
    }

    public function test_customer_with_invoices_view_cannot_list_or_show_provider_to_platform_invoices(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $owner = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'API Invoice Customer',
            'email' => 'api-inv-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);

        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        $perm = $this->ensurePermission('invoices.view');
        $customerUser->directPermissions()->syncWithoutDetaching([$perm->id]);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $providerInv = $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'provider_to_platform',
            'customer_visible' => false,
            'customer_type' => 'b2b',
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Provider line',
                'quantity' => 1,
                'unit_price' => 200,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $customerInv = $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'platform_to_customer',
            'customer_visible' => true,
            'customer_id' => $customer->id,
            'customer_type' => 'b2b',
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Customer line',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $idx = $this->actingAsUser($customerUser)->getJson('/api/v1/invoices');
        $idx->assertOk();
        $ids = collect($idx->json('data.data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($customerInv->id, $ids);
        $this->assertNotContains($providerInv->id, $ids);

        $this->actingAsUser($customerUser)->getJson('/api/v1/invoices/'.$providerInv->id)->assertNotFound();
        $this->actingAsUser($customerUser)->getJson('/api/v1/invoices/'.$customerInv->id)->assertOk();

        $ownerIdx = $this->actingAsUser($owner)->getJson('/api/v1/invoices');
        $ownerIdx->assertOk();
        $ownerIds = collect($ownerIdx->json('data.data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($providerInv->id, $ownerIds);
        $this->assertContains($customerInv->id, $ownerIds);
    }

    public function test_work_order_show_hides_non_customer_facing_invoice_for_customer_actor(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $owner = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'WO Invoice Customer',
            'email' => 'wo-inv-'.Str::random(6).'@test.sa',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $owner->id,
            'plate_number' => 'WO-INV-'.Str::upper(Str::random(3)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2023,
            'is_active' => true,
        ]);

        $wo = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $owner->id,
            'order_number' => 'WO-ISO-'.Str::upper(Str::random(4)),
            'status' => WorkOrderStatus::Completed,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $providerInv = $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'provider_to_platform',
            'customer_visible' => false,
            'customer_type' => 'b2b',
            'vehicle_id' => $vehicle->id,
            'source_type' => WorkOrder::class,
            'source_id' => $wo->id,
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Provider line',
                'quantity' => 1,
                'unit_price' => 50,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $wo->update(['invoice_id' => $providerInv->id]);

        $customerUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);
        foreach (['work_orders.view', 'invoices.view'] as $name) {
            $p = $this->ensurePermission($name);
            $customerUser->directPermissions()->syncWithoutDetaching([$p->id]);
        }

        $res = $this->actingAsUser($customerUser)->getJson('/api/v1/work-orders/'.$wo->id);
        $res->assertOk();
        $this->assertNull($res->json('data.invoice'));
        $this->assertArrayNotHasKey('invoice_id', $res->json('data'));
    }
}
