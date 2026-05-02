<?php

namespace Tests\Feature\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Branch $branch;

    private User $user;

    private Customer $customer;

    private Vehicle $vehicle;

    private WorkOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createCompany();
        $this->branch = $this->createBranch($this->company);
        $this->user = $this->createUser($this->company, $this->branch);
        $this->customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $this->company->id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Test Customer',
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $this->vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $this->customer->id,
            'created_by_user_id' => $this->user->id,
            'plate_number' => 'TEST-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);
        $this->service = app(WorkOrderService::class);
    }

    private function createOrder(array $extra = []): WorkOrder
    {
        return $this->service->create(
            array_merge([
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ], $extra),
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );
    }

    private function pathToInProgress(WorkOrder $order): WorkOrder
    {
        $order->refresh();
        if ($order->status === WorkOrderStatus::PendingManagerApproval) {
            $this->service->transition($order, WorkOrderStatus::Approved);
            $order->refresh();
        }

        return $this->service->transition($order, WorkOrderStatus::InProgress);
    }

    public function test_create_work_order_defaults_to_approved_for_service_center(): void
    {
        $order = $this->createOrder();

        $this->assertEquals(WorkOrderStatus::Approved, $order->status);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_create_work_order_retail_defaults_to_pending_manager_approval(): void
    {
        $retail = $this->createCompany([
            'settings' => ['business_profile' => ['business_type' => 'retail']],
        ]);
        $retailBranch = $this->createBranch($retail);
        $retailUser = $this->createUser($retail, $retailBranch);
        $retailCustomer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $retail->id,
            'created_by_user_id' => $retailUser->id,
            'name' => 'Retail Customer',
            'type' => 'individual',
            'is_active' => true,
        ]);
        $retailVehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $retail->id,
            'branch_id' => $retailBranch->id,
            'customer_id' => $retailCustomer->id,
            'created_by_user_id' => $retailUser->id,
            'plate_number' => 'RTL-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);
        $order = $this->service->create(
            [
                'customer_id' => $retailCustomer->id,
                'vehicle_id' => $retailVehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ],
            $retail->id,
            $retailBranch->id,
            $retailUser->id,
        );

        $this->assertEquals(WorkOrderStatus::PendingManagerApproval, $order->status);
    }

    public function test_pending_manager_can_transition_to_approved_then_in_progress(): void
    {
        $order = $this->createOrder();
        $updated = $this->pathToInProgress($order);

        $this->assertEquals(WorkOrderStatus::InProgress, $updated->status);
        $this->assertNotNull($updated->started_at);
    }

    public function test_in_progress_can_complete(): void
    {
        $order = $this->createOrder();
        $this->pathToInProgress($order);
        $order->refresh();
        $this->prepareWorkOrderForCompletedTransition($order);

        $updated = $this->service->transition($order, WorkOrderStatus::Completed, [
            'technician_notes' => 'Changed oil and filter.',
            'mileage_out' => 50000,
        ]);

        $this->assertEquals(WorkOrderStatus::Completed, $updated->status);
        $this->assertEquals('Changed oil and filter.', $updated->technician_notes);
        $this->assertNotNull($updated->completed_at);
    }

    public function test_completed_can_be_delivered(): void
    {
        $order = $this->createOrder();
        $this->pathToInProgress($order);
        $order->refresh();
        $this->prepareWorkOrderForCompletedTransition($order, [
            'technician_notes' => 'Ready for delivery.',
            'mileage_out' => 50100,
        ]);
        $this->service->transition($order, WorkOrderStatus::Completed);
        $order->refresh();

        $updated = $this->service->transition($order, WorkOrderStatus::Delivered);

        $this->assertEquals(WorkOrderStatus::Delivered, $updated->status);
        $this->assertNotNull($updated->delivered_at);
    }

    public function test_delivered_order_cannot_transition_further(): void
    {
        $order = $this->createOrder();
        $this->pathToInProgress($order);
        $order->refresh();
        $this->prepareWorkOrderForCompletedTransition($order, [
            'technician_notes' => 'Delivered path.',
            'mileage_out' => 50200,
        ]);
        $this->service->transition($order, WorkOrderStatus::Completed);
        $order->refresh();
        $this->service->transition($order, WorkOrderStatus::Delivered);
        $order->refresh();

        $this->expectException(\DomainException::class);
        $this->service->transition($order, WorkOrderStatus::Cancelled);
    }

    public function test_invalid_transition_throws_domain_exception(): void
    {
        $order = $this->createOrder();

        $this->expectException(\DomainException::class);
        $this->service->transition($order, WorkOrderStatus::Delivered);
    }

    public function test_can_cancel_pending_manager_approval_order(): void
    {
        $retail = $this->createCompany([
            'settings' => ['business_profile' => ['business_type' => 'retail']],
        ]);
        $retailBranch = $this->createBranch($retail);
        $retailUser = $this->createUser($retail, $retailBranch);
        $retailCustomer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $retail->id,
            'created_by_user_id' => $retailUser->id,
            'name' => 'Retail Cancel',
            'type' => 'individual',
            'is_active' => true,
        ]);
        $retailVehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $retail->id,
            'branch_id' => $retailBranch->id,
            'customer_id' => $retailCustomer->id,
            'created_by_user_id' => $retailUser->id,
            'plate_number' => 'RTL-CXL',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);
        $order = $this->service->create(
            [
                'customer_id' => $retailCustomer->id,
                'vehicle_id' => $retailVehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ],
            $retail->id,
            $retailBranch->id,
            $retailUser->id,
        );
        $updated = $this->service->transition($order, WorkOrderStatus::Cancelled);

        $this->assertEquals(WorkOrderStatus::Cancelled, $updated->status);
    }

    public function test_can_hold_and_resume(): void
    {
        $order = $this->createOrder();
        $this->pathToInProgress($order);
        $order->refresh();
        $this->service->transition($order, WorkOrderStatus::OnHold);
        $order->refresh();

        $this->assertEquals(WorkOrderStatus::OnHold, $order->status);

        $resumed = $this->service->transition($order, WorkOrderStatus::InProgress);
        $this->assertEquals(WorkOrderStatus::InProgress, $resumed->status);
    }

    public function test_deletion_allowed_for_queue_and_cancelled_blocked_for_approved(): void
    {
        $this->createActiveSubscription($this->company);

        $retail = $this->createCompany([
            'settings' => ['business_profile' => ['business_type' => 'retail']],
        ]);
        $retailBranch = $this->createBranch($retail);
        $retailUser = $this->createUser($retail, $retailBranch);
        $this->createActiveSubscription($retail);
        $retailCustomer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $retail->id,
            'created_by_user_id' => $retailUser->id,
            'name' => 'Retail Del',
            'type' => 'individual',
            'is_active' => true,
        ]);
        $retailVehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $retail->id,
            'branch_id' => $retailBranch->id,
            'customer_id' => $retailCustomer->id,
            'created_by_user_id' => $retailUser->id,
            'plate_number' => 'RTL-DEL',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        $queued = $this->service->create(
            [
                'customer_id' => $retailCustomer->id,
                'vehicle_id' => $retailVehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ],
            $retail->id,
            $retailBranch->id,
            $retailUser->id,
        );
        $this->actingAs($retailUser, 'sanctum')
            ->deleteJson("/api/v1/work-orders/{$queued->id}")
            ->assertStatus(200);
        // API uses soft-delete; order_number unique prevents reusing the same sequence slot in this company.
        $queued->forceDelete();

        // إعادة سياق المستأجر لمستخدم الورشة قبل إنشاء أمر معتمد للشركة الأساسية.
        $this->actingAs($this->user, 'sanctum');

        $approved = $this->createOrder();
        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/work-orders/{$approved->id}")
            ->assertStatus(422);

        $cancelled = $this->service->create(
            [
                'customer_id' => $retailCustomer->id,
                'vehicle_id' => $retailVehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ],
            $retail->id,
            $retailBranch->id,
            $retailUser->id,
        );
        $this->actingAs($retailUser, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$cancelled->id}/status", [
                'status' => 'cancelled',
                'version' => $cancelled->version,
            ])
            ->assertOk();
        $cancelled->refresh();
        $this->actingAs($retailUser, 'sanctum')
            ->deleteJson("/api/v1/work-orders/{$cancelled->id}")
            ->assertStatus(200);
    }

    public function test_version_increments_on_each_transition(): void
    {
        $order = $this->createOrder();
        $this->assertEquals(0, $order->version);

        $this->service->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();
        $this->assertEquals(1, $order->version);

        $this->service->transition($order, WorkOrderStatus::OnHold);
        $order->refresh();
        $this->assertEquals(2, $order->version);
    }
}
