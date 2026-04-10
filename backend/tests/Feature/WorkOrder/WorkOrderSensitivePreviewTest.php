<?php

namespace Tests\Feature\WorkOrder;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkOrderSensitivePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rejects_approve_without_sensitive_preview_token(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Prev Test',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'PREV-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $this->assertSame(WorkOrderStatus::PendingManagerApproval, $order->status);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
            ]);

        $response->assertStatus(422);
    }

    public function test_api_approves_with_valid_preview_token(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'Prev Test 2',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'PREV-2',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $order = app(WorkOrderService::class)->create(
            [
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'items' => [['item_type' => 'service', 'name' => 'Line', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15]],
            ],
            $company->id,
            $branch->id,
            $user->id,
        );

        $token = $this->obtainSensitivePreviewToken(
            $user,
            SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
            [(int) $order->id],
        );

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/work-orders/{$order->id}/status", [
                'status' => 'approved',
                'version' => $order->version,
                'sensitive_preview_token' => $token,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'approved');
    }
}
