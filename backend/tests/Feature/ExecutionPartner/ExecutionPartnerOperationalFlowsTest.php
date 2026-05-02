<?php

namespace Tests\Feature\ExecutionPartner;

use App\Enums\PurchaseStatus;
use App\Enums\WorkOrderItemType;
use App\Enums\WorkOrderStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * عمليات حقيقية لواجهة مزوّد الخدمة / شريك تنفيذ المنصة — بدون صف اشتراك، مع استثناء الحصص حيث يُطبَّق.
 */
class ExecutionPartnerOperationalFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function enablePlatformExecutionPartner(Company $company): void
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $profile = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $matrix = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];
        $matrix['platform_execution_partner'] = true;
        $profile['feature_matrix'] = $matrix;
        $profile['business_type'] = $profile['business_type'] ?? 'service_center';
        $settings['business_profile'] = $profile;
        $company->update(['settings' => $settings]);
    }

    public function test_execution_partner_can_create_branch_without_subscription(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/branches', [
                'name' => 'فرع تنفيذ',
                'name_ar' => 'فرع تنفيذ',
                'code' => 'EP2',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'فرع تنفيذ');
    }

    public function test_execution_partner_can_create_purchase_claim_without_subscription(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();
        $branch = $t['branch'];
        $user = $t['user'];

        $supplier = Supplier::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'مورّد منصّة',
            'code' => 'PLAT-'.Str::upper(Str::random(4)),
            'is_active' => true,
            'status' => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $user->id,
            'reference_number' => 'PO-EP-CLAIM-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 1500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 1500,
            'paid_amount' => 0,
            'notes' => 'test platform settlement claim',
        ]);

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/purchase-claims', [
                'title' => 'صرف مستحقات',
                'purchase_ids' => [$purchase->id],
                'description' => 'طلب صرف مستحقات لمزوّد الخدمة — اختبار تلقائي',
                'requested_amount' => 1500.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.purchases.0.id', $purchase->id);
    }

    public function test_execution_partner_intake_lookup_returns_service_lines_for_active_order(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();
        $branch = $t['branch'];
        $user = $t['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'عميل اختبار EP',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'EPX 9999',
            'make' => 'Test',
            'model' => 'Vehicle',
            'year' => 2024,
            'is_active' => true,
        ]);

        $service = Service::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'خدمة اختبار',
            'code' => 'SVC-EP-'.Str::upper(Str::random(4)),
            'base_price' => 100,
            'tax_rate' => 15,
            'is_active' => true,
        ]);

        $order = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $user->id,
            'order_number' => 'WO-EP-'.Str::upper(Str::random(6)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 100,
            'actual_total' => 0,
            'version' => 0,
        ]);

        WorkOrderItem::create([
            'company_id' => $company->id,
            'work_order_id' => $order->id,
            'service_id' => $service->id,
            'item_type' => WorkOrderItemType::Service,
            'name' => $service->name,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'tax_rate' => 15,
            'tax_amount' => 15,
            'subtotal' => 100,
            'total' => 115,
        ]);

        $plate = 'EPX 9999';

        $res = $this->actingAsUser($user)
            ->getJson('/api/v1/work-orders/intake-lookup?'.http_build_query(['plate_number' => $plate]));

        $res->assertOk();
        $res->assertJsonPath('data.show_service_lines', true);
        $lines = $res->json('data.service_lines');
        $this->assertIsArray($lines);
        $this->assertGreaterThanOrEqual(1, count($lines));
        $this->assertSame($service->name, $lines[0]['name'] ?? null);
        $res->assertJsonPath('data.work_order.is_active', true);
        $res->assertJsonPath('data.execution.can_execute_now', true);
    }

    public function test_execution_partner_can_list_platform_settlement_purchases_without_subscription(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();
        $branch = $t['branch'];

        $supplier = Supplier::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $t['user']->id,
            'name' => 'مورّد منصّة',
            'code' => 'PLAT-'.Str::upper(Str::random(4)),
            'is_active' => true,
            'status' => 'active',
        ]);

        Purchase::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $t['user']->id,
            'reference_number' => 'PO-EP-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 500,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 500,
            'paid_amount' => 0,
            'notes' => 'test platform settlement',
        ]);

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/purchases?platform_settlement=1')
            ->assertOk();
    }

    public function test_intake_lookup_camera_accepts_data_url_prefix_on_image(): void
    {
        $t = $this->createTenant();
        $payload = random_bytes(180);
        $this->assertGreaterThan(100, strlen($payload));
        $wrapped = 'data:image/jpeg;base64,'.base64_encode($payload);

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/work-orders/intake-lookup-camera', ['image' => $wrapped])
            ->assertOk()
            ->assertJsonStructure(['data' => ['camera_lookup', 'lookup']]);
    }

    public function test_technician_can_use_intake_lookup_camera_without_users_update_permission(): void
    {
        $t = $this->createTenant('technician');
        $payload = random_bytes(200);
        $this->assertGreaterThan(100, strlen($payload));

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/work-orders/intake-lookup-camera', [
                'image' => base64_encode($payload),
            ])
            ->assertOk()
            ->assertJsonPath('data.camera_lookup.used', true);
    }

    public function test_platform_admin_can_intake_lookup_on_behalf_of_execution_partner_company(): void
    {
        Config::set('platform.admin_enabled', true);
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();
        $branch = $t['branch'];
        $user = $t['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'عميل تفويض',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'DLG 8001',
            'make' => 'Test',
            'model' => 'Delegate',
            'year' => 2024,
            'is_active' => true,
        ]);
        $service = Service::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'خدمة تفويض',
            'code' => 'SVC-DLG-'.Str::upper(Str::random(4)),
            'base_price' => 100,
            'tax_rate' => 15,
            'is_active' => true,
        ]);
        $order = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $user->id,
            'order_number' => 'WO-DLG-'.Str::upper(Str::random(6)),
            'status' => WorkOrderStatus::InProgress,
            'priority' => 'normal',
            'estimated_total' => 100,
            'actual_total' => 0,
            'version' => 0,
        ]);
        WorkOrderItem::create([
            'company_id' => $company->id,
            'work_order_id' => $order->id,
            'service_id' => $service->id,
            'item_type' => WorkOrderItemType::Service,
            'name' => $service->name,
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'tax_rate' => 15,
            'tax_amount' => 15,
            'subtotal' => 100,
            'total' => 115,
        ]);

        $this->createStandalonePlatformOperator('plat-delegate@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', 'plat-delegate@platform.test')->firstOrFail();

        $q = http_build_query([
            'plate_number' => 'DLG 8001',
            'on_behalf_company_id' => (string) $company->id,
        ]);

        $res = $this->actingAsUser($platformUser)
            ->getJson('/api/v1/work-orders/intake-lookup?'.$q);

        $res->assertOk()
            ->assertJsonPath('data.delegation.by_platform', true)
            ->assertJsonPath('data.delegation.company_id', $company->id)
            ->assertJsonPath('data.work_order.id', $order->id)
            ->assertJsonPath('data.execution.can_execute_now', true);
    }

    public function test_platform_admin_can_show_work_order_on_behalf_of_execution_partner(): void
    {
        Config::set('platform.admin_enabled', true);
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();
        $branch = $t['branch'];
        $user = $t['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'عميل عرض أمر',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'SHW 9001',
            'make' => 'Test',
            'model' => 'ShowWo',
            'year' => 2024,
            'is_active' => true,
        ]);
        $order = WorkOrder::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'created_by_user_id' => $user->id,
            'order_number' => 'WO-SHW-'.Str::upper(Str::random(6)),
            'status' => WorkOrderStatus::Draft,
            'priority' => 'normal',
            'estimated_total' => 0,
            'actual_total' => 0,
            'version' => 0,
        ]);

        $this->createStandalonePlatformOperator('plat-wo-show@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', 'plat-wo-show@platform.test')->firstOrFail();

        $this->actingAsUser($platformUser)
            ->withHeaders(['X-On-Behalf-Company-Id' => (string) $company->id])
            ->getJson('/api/v1/work-orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.company_id', $company->id);
    }

    public function test_platform_admin_can_create_service_on_behalf_of_execution_partner(): void
    {
        Config::set('platform.admin_enabled', true);
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();

        $this->createStandalonePlatformOperator('plat-svc-create@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', 'plat-svc-create@platform.test')->firstOrFail();

        $this->actingAsUser($platformUser)
            ->withHeaders(['X-On-Behalf-Company-Id' => (string) $company->id])
            ->postJson('/api/v1/services', [
                'name' => 'خدمة من المنصّة',
                'base_price' => 120,
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.name', 'خدمة من المنصّة');
    }

    public function test_platform_admin_can_create_product_on_behalf_of_execution_partner(): void
    {
        Config::set('platform.admin_enabled', true);
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $company = $t['company']->fresh();

        $this->createStandalonePlatformOperator('plat-prd-create@platform.test', [
            'platform_role' => 'platform_admin',
        ]);
        $platformUser = User::where('email', 'plat-prd-create@platform.test')->firstOrFail();

        $this->actingAsUser($platformUser)
            ->withHeaders(['X-On-Behalf-Company-Id' => (string) $company->id])
            ->postJson('/api/v1/products', [
                'name' => 'منتج من المنصّة',
                'sale_price' => 99,
                'product_type' => 'service',
            ])
            ->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.name', 'منتج من المنصّة');
    }
}
