<?php

namespace Tests\Feature\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OptimisticLockingTest extends TestCase
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
        $this->createActiveSubscription($this->company);

        $this->customer = Customer::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'created_by_user_id' => $this->user->id,
            'name'               => 'Locking Test Customer',
            'customer_type'      => 'individual',
            'is_active'          => true,
        ]);

        $this->vehicle = Vehicle::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'branch_id'          => $this->branch->id,
            'customer_id'        => $this->customer->id,
            'created_by_user_id' => $this->user->id,
            'plate_number'       => 'LOCK-001',
            'make'               => 'BMW',
            'model'              => 'X5',
            'year'               => 2020,
        ]);

        $this->service = app(WorkOrderService::class);
    }

    private function createOrder(): WorkOrder
    {
        return $this->service->create(
            [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'items' => [$this->minimalWorkOrderLineItem()],
            ],
            $this->company->id,
            $this->branch->id,
            $this->user->id,
        );
    }

    public function test_concurrent_transition_with_stale_version_throws_runtime_exception(): void
    {
        $order = $this->createOrder();

        $snapshot1 = clone $order;
        $snapshot2 = clone $order;

        $this->service->transition($snapshot1, WorkOrderStatus::Approved);
        $snapshot1->refresh();
        $this->service->transition($snapshot1, WorkOrderStatus::InProgress);

        $this->expectException(\RuntimeException::class);
        $this->service->transition($snapshot2, WorkOrderStatus::Cancelled);
    }

    public function test_concurrent_update_with_stale_version_throws_runtime_exception(): void
    {
        $order = $this->createOrder();

        $snapshot = clone $order;

        $this->service->update($order, [
            'version'  => $order->version,
            'priority' => 'high',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->update($snapshot, [
            'version'  => $snapshot->version,
            'priority' => 'urgent',
        ]);
    }

    public function test_api_status_transition_returns_409_on_version_conflict(): void
    {
        $order = $this->createOrder();

        $this->service->transition($order, WorkOrderStatus::Approved);
        $order->refresh();
        $this->service->transition($order, WorkOrderStatus::InProgress);
        $order->refresh();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status'  => 'on_hold',
                'version' => 0,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'RESOURCE_VERSION_MISMATCH')
            ->assertJsonPath('status', 409)
            ->assertJsonStructure(['message', 'trace_id']);
    }

    public function test_api_status_transition_returns_409_on_invalid_transition(): void
    {
        $order = $this->createOrder();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status'  => 'delivered',
                'version' => $order->version,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'TRANSITION_NOT_ALLOWED')
            ->assertJsonPath('status', 409)
            ->assertJsonStructure(['message', 'trace_id']);
    }

    public function test_successful_transition_returns_updated_version(): void
    {
        $order = $this->createOrder();

        $token = $this->obtainSensitivePreviewToken(
            $this->user,
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $token,
            ])
            ->assertOk();

        $order->refresh();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status'  => 'in_progress',
                'version' => $order->version,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'in_progress');
        $response->assertJsonPath('data.version', 2);
    }
}
