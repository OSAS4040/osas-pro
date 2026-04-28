<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrderBatch;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WorkOrderBulkApiTest extends TestCase
{
    public function test_bulk_creates_batch_and_work_orders_inline(): void
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
            'name' => 'Bulk Cust',
            'is_active' => true,
        ]);
        $v1 = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BULK-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);
        $v2 = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'BULK-2',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/bulk', [
                'vehicle_ids' => [$v1->id, $v2->id],
                'service_code' => 'oil_change',
            ]);

        $res->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.vehicle_count', 2);

        $uuid = (string) $res->json('data.batch_uuid');
        $this->assertNotSame('', $uuid);

        $batch = WorkOrderBatch::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame('bulk_api', $batch->source);
        $this->assertSame(2, $batch->items()->where('status', 'succeeded')->count());
        $this->assertSame(0, $batch->items()->where('status', 'failed')->count());
    }

    public function test_bulk_idempotency_key_replays_same_batch(): void
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
            'name' => 'Idem Cust',
            'is_active' => true,
        ]);
        $v = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'IDEM-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $key = 'idem-bulk-'.Str::uuid()->toString();

        $first = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/bulk', [
                'vehicle_ids' => [$v->id],
                'service_code' => 'oil_change',
            ], ['Idempotency-Key' => $key]);
        $first->assertStatus(202);

        $second = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/bulk', [
                'vehicle_ids' => [$v->id],
                'service_code' => 'oil_change',
            ], ['Idempotency-Key' => $key]);
        $second->assertStatus(200)
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(1, WorkOrderBatch::query()->where('company_id', $company->id)->where('idempotency_key', $key)->count());
    }

    public function test_batch_status_endpoint_returns_counts(): void
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
            'name' => 'Show Cust',
            'is_active' => true,
        ]);
        $v = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'SHOW-1',
            'make' => 'X',
            'model' => 'Y',
            'year' => 2024,
        ]);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/work-orders/bulk', [
                'vehicle_ids' => [$v->id],
                'service_code' => 'oil_change',
            ]);
        $uuid = (string) $res->json('data.batch_uuid');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/work-orders/batches/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.counts.succeeded', 1)
            ->assertJsonPath('data.counts.pending', 0);
    }
}
