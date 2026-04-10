<?php

declare(strict_types=1);

namespace Tests\Feature\WorkOrder;

use App\Models\Company;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkOrderOrderNumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_consecutive_creates_use_incrementing_suffixes(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'name' => 'Seq Customer',
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'SEQ-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        $service = app(WorkOrderService::class);

        $payload = static fn (string $name) => [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => $name, 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 15],
            ],
        ];

        $o1 = $service->create($payload('a'), $company->id, $branch->id, $user->id);
        $o2 = $service->create($payload('b'), $company->id, $branch->id, $user->id);
        $o3 = $service->create($payload('c'), $company->id, $branch->id, $user->id);

        $this->assertSame(sprintf('WO-%d-%06d', $company->id, 1), $o1->order_number);
        $this->assertSame(sprintf('WO-%d-%06d', $company->id, 2), $o2->order_number);
        $this->assertSame(sprintf('WO-%d-%06d', $company->id, 3), $o3->order_number);

        $this->assertSame(1, WorkOrder::query()->where('company_id', $company->id)->where('order_number', $o1->order_number)->count());
        $this->assertSame(1, WorkOrder::query()->where('company_id', $company->id)->where('order_number', $o2->order_number)->count());
    }

    public function test_continues_after_pre_existing_suffix_in_sequence_table(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);

        $now = now();
        DB::table('work_order_sequences')->insert([
            'company_id' => $company->id,
            'last_allocated' => 12,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'name' => 'Seq Customer 2',
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'SEQ-002',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        $service = app(WorkOrderService::class);
        $payload = [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => 'x', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ],
        ];

        $order = $service->create($payload, $company->id, $branch->id, $user->id);

        $this->assertSame(sprintf('WO-%d-%06d', $company->id, 13), $order->order_number);
    }

    public function test_bootstraps_suffix_when_work_orders_exist_without_sequence_row(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'name' => 'Legacy gap customer',
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'LEG-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        WorkOrder::withoutEvents(function () use ($company, $branch, $user, $customer, $vehicle) {
            WorkOrder::query()->create([
                'uuid' => Str::uuid(),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'created_by_user_id' => $user->id,
                'order_number' => sprintf('WO-%d-%06d', $company->id, 7),
                'status' => WorkOrderStatus::Draft,
                'estimated_total' => 0,
            ]);
        });

        $this->assertFalse(
            DB::table('work_order_sequences')->where('company_id', $company->id)->exists(),
            'Scenario requires no sequence row until first service create',
        );

        $service = app(WorkOrderService::class);
        $order = $service->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => 'after legacy', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ],
        ], $company->id, $branch->id, $user->id);

        $this->assertSame(sprintf('WO-%d-%06d', $company->id, 8), $order->order_number);
    }

    public function test_sequence_state_unwinds_when_work_order_insert_fails(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'name' => 'Rollback Customer',
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'RB-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        $service = app(WorkOrderService::class);
        $valid = [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => 'ok', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ],
        ];
        $service->create($valid, $company->id, $branch->id, $user->id);
        $seqAfterOk = (int) DB::table('work_order_sequences')->where('company_id', $company->id)->value('last_allocated');

        $bad = array_merge($valid, ['vehicle_id' => 9_999_999_997]);

        try {
            $service->create($bad, $company->id, $branch->id, $user->id);
            $this->fail('Expected FK failure for invalid vehicle_id');
        } catch (QueryException) {
            // expected
        }

        $seqAfterFail = (int) DB::table('work_order_sequences')->where('company_id', $company->id)->value('last_allocated');
        $this->assertSame($seqAfterOk, $seqAfterFail);
    }

    public function test_two_companies_have_independent_sequences(): void
    {
        $service = app(WorkOrderService::class);
        $payload = static fn (Customer $c, Vehicle $v, string $name) => [
            'customer_id' => $c->id,
            'vehicle_id' => $v->id,
            'items' => [
                ['item_type' => 'labor', 'name' => $name, 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ],
        ];

        $orders = [];
        foreach ([$this->setupTenant('A'), $this->setupTenant('B')] as $ctx) {
            $orders[] = $service->create(
                $payload($ctx['customer'], $ctx['vehicle'], 't'),
                $ctx['company']->id,
                $ctx['branch']->id,
                $ctx['user']->id,
            );
        }

        $this->assertSame(sprintf('WO-%d-%06d', $orders[0]->company_id, 1), $orders[0]->order_number);
        $this->assertSame(sprintf('WO-%d-%06d', $orders[1]->company_id, 1), $orders[1]->order_number);
        $this->assertNotSame($orders[0]->company_id, $orders[1]->company_id);
    }

    public function test_create_does_not_run_count_star_on_work_orders_table(): void
    {
        $company = $this->createCompany();
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'name' => 'Count Customer',
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'CT-001',
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        DB::enableQueryLog();
        $service = app(WorkOrderService::class);
        $service->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'items' => [
                ['item_type' => 'labor', 'name' => 'x', 'quantity' => 1, 'unit_price' => 1, 'tax_rate' => 0],
            ],
        ], $company->id, $branch->id, $user->id);

        $violations = [];
        foreach (DB::getQueryLog() as $entry) {
            $sql = strtolower($entry['query']);
            if (! str_contains($sql, 'work_orders')) {
                continue;
            }
            if (preg_match('/\bcount\s*\(\s*\*\s*\)/', $sql)) {
                $violations[] = $entry['query'];
            }
        }
        DB::disableQueryLog();

        $this->assertSame([], $violations, 'COUNT(*) on work_orders should not run during WorkOrderService::create');
    }

    /**
     * @return array{company: Company, branch: \App\Models\Branch, user: User, customer: Customer, vehicle: Vehicle}
     */
    private function setupTenant(string $suffix): array
    {
        $company = $this->createCompany(['name' => 'Co '.$suffix]);
        $branch = $this->createBranch($company);
        $user = $this->createUser($company, $branch);
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'created_by_user_id' => $user->id,
            'name' => 'C '.$suffix,
            'customer_type' => 'individual',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'uuid' => Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $user->id,
            'plate_number' => 'P-'.$suffix,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2022,
        ]);

        return compact('company', 'branch', 'user', 'customer', 'vehicle');
    }
}
