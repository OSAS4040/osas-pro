<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * سيناريوهات HTTP واقعية: ورشة (service_center) تنشئ أمر عملاً معتمداً وتُكمّل التنفيذ؛
 * عميل البوابة يطلع تقارير الإنجاز ويُحظر عليه انتقالات التشغيل الحساسة.
 * متجر (retail) يبقى في طابور المدير حتى الاعتماد — ما لم يكن المستأجر شريك تنفيذ منصة (تنفيذ مباشر).
 */
final class WorkshopAndCustomerPortalWorkOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_creates_approved_order_and_customer_portal_sees_completed_row(): void
    {
        $t = $this->createTenant();
        $company = $t['company'];
        $branch = $t['branch'];
        $staff = $t['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Portal Flow Customer',
            'email' => 'portal-flow-'.Str::random(8).'@test.sa',
            'is_active' => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'PF-'.Str::upper(Str::random(4)),
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2023,
            'is_active' => true,
        ]);

        $createRes = $this->actingAsUser($staff)->postJson('/api/v1/work-orders', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'service', 'name' => 'Oil service', 'quantity' => 1, 'unit_price' => 120, 'tax_rate' => 15],
            ],
        ]);

        $createRes->assertCreated();
        $createRes->assertJsonPath('data.status', 'approved');
        $woId = (int) $createRes->json('data.id');
        $version = (int) $createRes->json('data.version');

        $this->actingAsUser($staff)
            ->patchJson("/api/v1/work-orders/{$woId}/status", [
                'status' => 'in_progress',
                'version' => $version,
            ])
            ->assertOk();

        $order = WorkOrder::query()->findOrFail($woId);
        $this->prepareWorkOrderForCompletedTransition($order);
        $order->refresh();

        $this->actingAsUser($staff)
            ->patchJson("/api/v1/work-orders/{$woId}/status", [
                'status' => 'completed',
                'version' => $order->version,
                'technician_notes' => 'E2E: oil and filter replaced.',
                'mileage_out' => 45000,
            ])
            ->assertOk();

        $portalUser = $this->createUser($company, $branch, 'customer', [
            'email' => $customer->email,
            'customer_id' => $customer->id,
        ]);

        $from = Carbon::now()->subDays(7)->toDateString();
        $to = Carbon::now()->addDay()->toDateString();

        $rep = $this->actingAsUser($portalUser)
            ->getJson('/api/v1/customer-portal/reports/work-orders-completed?'.http_build_query([
                'from' => $from,
                'to' => $to,
            ]));
        $rep->assertOk();
        $ids = collect($rep->json('data.rows') ?? [])->pluck('id')->all();
        $this->assertContains($woId, $ids);

        $fresh = WorkOrder::query()->findOrFail($woId);
        $this->actingAsUser($portalUser)
            ->patchJson("/api/v1/work-orders/{$woId}/status", [
                'status' => 'delivered',
                'version' => (int) $fresh->version,
            ])
            ->assertForbidden();
    }

    public function test_retail_tenant_starts_in_manager_queue_until_approved(): void
    {
        $company = $this->createCompany([
            'settings' => ['business_profile' => ['business_type' => 'retail']],
        ]);
        $branch = $this->createBranch($company);
        $staff = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Retail C',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'RT-'.Str::upper(Str::random(4)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $r = $this->actingAsUser($staff)->postJson('/api/v1/work-orders', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 15],
            ],
        ]);
        $r->assertCreated();
        $r->assertJsonPath('data.status', 'pending_manager_approval');
    }

    public function test_platform_execution_partner_retail_starts_approved_for_direct_execution(): void
    {
        $company = $this->createCompany([
            'settings' => ['business_profile' => ['business_type' => 'retail']],
        ]);
        Config::set('tenant_features.platform_execution_partner_company_ids', [$company->id]);
        Config::set('tenant_features.platform_execution_partner_company_emails', []);

        $branch = $this->createBranch($company);
        $staff = $this->createUser($company, $branch, 'owner');
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Retail EP',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number' => 'EP-'.Str::upper(Str::random(4)),
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $r = $this->actingAsUser($staff)->postJson('/api/v1/work-orders', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 15],
            ],
        ]);
        $r->assertCreated();
        $r->assertJsonPath('data.status', 'approved');
    }
}
