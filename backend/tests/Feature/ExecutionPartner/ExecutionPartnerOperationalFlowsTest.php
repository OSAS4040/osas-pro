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
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/purchase-claims', [
                'title' => 'صرف مستحقات',
                'description' => 'طلب صرف مستحقات لمزوّد الخدمة — اختبار تلقائي',
                'requested_amount' => 1500.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
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
}
