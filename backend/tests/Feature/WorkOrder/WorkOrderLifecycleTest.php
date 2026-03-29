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

        $this->company  = $this->createCompany();
        $this->branch   = $this->createBranch($this->company);
        $this->user     = $this->createUser($this->company, $this->branch);
        $this->customer = Customer::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'created_by_user_id' => $this->user->id,
            'name'               => 'Test Customer',
            'customer_type'      => 'individual',
            'is_active'          => true,
        ]);
        $this->vehicle  = Vehicle::create([
            'uuid'                 => Str::uuid(),
            'company_id'           => $this->company->id,
            'branch_id'            => $this->branch->id,
            'customer_id'          => $this->customer->id,
            'created_by_user_id'   => $this->user->id,
            'plate_number'         => 'TEST-001',
            'make'                 => 'Toyota',
            'model'                => 'Camry',
            'year'                 => 2022,
        ]);
        $this->service = app(WorkOrderService::class);
    }

    private function createOrder(array $extra = []): WorkOrder
    {
        return $this->service->create(
            array_merge(['customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id], $extra),
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );
    }

    public function test_create_work_order_defaults_to_pending(): void
    {
        $order = $this->createOrder();

        $this->assertEquals(WorkOrderStatus::Pending, $order->status);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_pending_can_transition_to_in_progress(): void
    {
        $order   = $this->createOrder();
        $updated = $this->service->transition($order, WorkOrderStatus::InProgress);

        $this->assertEquals(WorkOrderStatus::InProgress, $updated->status);
        $this->assertNotNull($updated->started_at);
    }

    public function test_in_progress_can_complete(): void
    {
        $order = $this->createOrder();
        $this->service->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();

        $updated = $this->service->transition($order, WorkOrderStatus::Completed, [
            'technician_notes' => 'Changed oil and filter.',
            'mileage_out'      => 50000,
        ]);

        $this->assertEquals(WorkOrderStatus::Completed, $updated->status);
        $this->assertEquals('Changed oil and filter.', $updated->technician_notes);
        $this->assertNotNull($updated->completed_at);
    }

    public function test_completed_can_be_delivered(): void
    {
        $order = $this->createOrder();
        $this->service->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();
        $this->service->transition($order, WorkOrderStatus::Completed);
        $order->refresh();

        $updated = $this->service->transition($order, WorkOrderStatus::Delivered);

        $this->assertEquals(WorkOrderStatus::Delivered, $updated->status);
        $this->assertNotNull($updated->delivered_at);
    }

    public function test_delivered_order_cannot_transition_further(): void
    {
        $order = $this->createOrder();
        $this->service->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();
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

    public function test_can_cancel_pending_order(): void
    {
        $order   = $this->createOrder();
        $updated = $this->service->transition($order, WorkOrderStatus::Cancelled);

        $this->assertEquals(WorkOrderStatus::Cancelled, $updated->status);
    }

    public function test_can_hold_and_resume(): void
    {
        $order = $this->createOrder();
        $this->service->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();
        $this->service->transition($order, WorkOrderStatus::OnHold);
        $order->refresh();

        $this->assertEquals(WorkOrderStatus::OnHold, $order->status);

        $resumed = $this->service->transition($order, WorkOrderStatus::InProgress);
        $this->assertEquals(WorkOrderStatus::InProgress, $resumed->status);
    }

    public function test_only_draft_and_cancelled_can_be_deleted(): void
    {
        $this->createActiveSubscription($this->company);
        $order = $this->createOrder();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/work-orders/{$order->id}");

        $response->assertStatus(422);

        $this->service->transition($order, WorkOrderStatus::Cancelled);
        $order->refresh();

        $response2 = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/work-orders/{$order->id}");

        $response2->assertStatus(200);
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
