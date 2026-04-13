<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalOperationsFeedTest extends TestCase
{
    private function feedUrl(): string
    {
        return '/api/v1/reporting/v1/operations/global-feed';
    }

    /**
     * @return array<string, string|int>
     */
    private function baseQuery(int $days = 7): array
    {
        return [
            'from'      => now()->subDays($days)->toDateString(),
            'to'        => now()->addDay()->toDateString(),
            'page'      => 1,
            'per_page'  => 50,
        ];
    }

    public function test_owner_receives_feed_envelope_and_items(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Feed Cust',
            'is_active'  => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number'       => 'FD-'.Str::upper(Str::random(4)),
            'make'               => 'X',
            'model'              => 'Y',
            'year'               => 2022,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $vehicle->id,
            'created_by_user_id' => $tenant['user']->id,
            'order_number'       => 'WO-GF-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query($this->baseQuery()));

        $res->assertOk()
            ->assertJsonPath('report.id', 'operations.global_feed')
            ->assertJsonPath('report.read_only', true)
            ->assertJsonPath('meta.pagination.page', 1)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_items_in_window',
                        'work_orders_count',
                        'invoices_count',
                        'payments_count',
                        'tickets_count',
                        'attention_count',
                    ],
                    'items',
                ],
                'meta' => ['pagination', 'financial_metrics_included', 'filters_applied'],
            ]);

        $items = $res->json('data.items');
        $this->assertIsArray($items);
        $this->assertGreaterThanOrEqual(1, count($items));
        $first = $items[0];
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('entity_route', $first);
        $this->assertArrayHasKey('read_only', $first);
        $this->assertTrue($first['read_only']);
    }

    public function test_technician_forbidden(): void
    {
        $tenant = $this->createTenant('technician');

        $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query($this->baseQuery()))
            ->assertForbidden();
    }

    public function test_invalid_company_id_returns_422(): void
    {
        $tenant = $this->createTenant('owner');
        $other = $this->createTenant('owner');

        $q = array_merge($this->baseQuery(), ['company_id' => $other['company']->id]);

        $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query($q))
            ->assertStatus(422);
    }

    public function test_other_company_data_not_leaked(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $custA = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $a['company']->id,
            'branch_id'  => $a['branch']->id,
            'type'       => 'b2c',
            'name'       => 'A Only',
            'is_active'  => true,
        ]);
        $vA = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $a['company']->id,
            'branch_id'          => $a['branch']->id,
            'customer_id'        => $custA->id,
            'created_by_user_id' => $a['user']->id,
            'plate_number'       => 'AX-'.Str::upper(Str::random(4)),
            'make'               => 'X',
            'model'              => 'Y',
            'year'               => 2022,
            'is_active'          => true,
        ]);
        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $a['company']->id,
            'branch_id'          => $a['branch']->id,
            'customer_id'        => $custA->id,
            'vehicle_id'         => $vA->id,
            'created_by_user_id' => $a['user']->id,
            'order_number'       => 'WO-A-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $custB = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $b['company']->id,
            'branch_id'  => $b['branch']->id,
            'type'       => 'b2c',
            'name'       => 'B Only',
            'is_active'  => true,
        ]);

        $v = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $b['company']->id,
            'branch_id'          => $b['branch']->id,
            'customer_id'        => $custB->id,
            'created_by_user_id' => $b['user']->id,
            'plate_number'       => 'BX-'.Str::upper(Str::random(4)),
            'make'               => 'X',
            'model'              => 'Y',
            'year'               => 2022,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $b['company']->id,
            'branch_id'          => $b['branch']->id,
            'customer_id'        => $custB->id,
            'vehicle_id'         => $v->id,
            'created_by_user_id' => $b['user']->id,
            'order_number'       => 'WO-B-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $items = $this->actingAsUser($a['user'])
            ->getJson($this->feedUrl().'?'.http_build_query($this->baseQuery()))
            ->json('data.items');

        $this->assertNotEmpty($items);
        foreach ($items as $row) {
            $this->assertSame($a['company']->id, (int) $row['company_id']);
        }
    }

    public function test_type_filter_limits_results(): void
    {
        $tenant = $this->createTenant('owner');

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query(array_merge($this->baseQuery(), ['types' => ['work_order']])));

        $res->assertOk();
        foreach ($res->json('data.items') as $row) {
            $this->assertSame('work_order', $row['type']);
        }
    }

    public function test_viewer_hides_payment_rows_and_amounts(): void
    {
        $tenant = $this->createTenant('viewer');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'V',
            'is_active'  => true,
        ]);

        $inv = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'invoice_number'     => 'INV-GF-'.Str::upper(Str::random(4)),
            'invoice_hash'       => hash('sha256', Str::random(16)),
            'invoice_counter'    => 1,
            'source_type'        => 'pos',
            'source_id'          => 0,
            'subtotal'           => 10,
            'tax_amount'         => 0,
            'total'              => 10,
            'paid_amount'        => 0,
            'due_amount'         => 10,
            'status'             => 'pending',
            'currency'           => 'SAR',
            'issued_at'          => now(),
        ]);

        Payment::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'invoice_id'         => $inv->id,
            'created_by_user_id' => $tenant['user']->id,
            'method'             => 'cash',
            'amount'             => 5,
            'currency'           => 'SAR',
            'status'             => 'completed',
            'trace_id'           => (string) Str::uuid(),
            'created_at'         => now(),
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query($this->baseQuery()));

        $res->assertOk()
            ->assertJsonPath('meta.financial_metrics_included', false);

        $types = array_column($res->json('data.items'), 'type');
        $this->assertNotContains('payment', $types);

        foreach ($res->json('data.items') as $row) {
            if ($row['type'] === 'invoice') {
                $this->assertNull($row['amount']);
                $this->assertTrue($row['financial_visibility_applied']);
            }
        }
    }

    public function test_pagination_respects_per_page(): void
    {
        $tenant = $this->createTenant('owner');

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query(array_merge($this->baseQuery(), ['per_page' => 2])));

        $res->assertOk();
        $this->assertLessThanOrEqual(2, count($res->json('data.items')));
        $this->assertSame(2, (int) $res->json('meta.pagination.per_page'));
    }

    public function test_ordering_is_desc_by_occurred_at(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Ord',
            'is_active'  => true,
        ]);

        $older = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'invoice_number'     => 'INV-OLD-'.Str::upper(Str::random(4)),
            'invoice_hash'       => hash('sha256', Str::random(16)),
            'invoice_counter'    => 1,
            'source_type'        => 'pos',
            'source_id'          => 0,
            'subtotal'           => 1,
            'tax_amount'         => 0,
            'total'              => 1,
            'paid_amount'        => 0,
            'due_amount'         => 1,
            'status'             => 'pending',
            'currency'           => 'SAR',
            'issued_at'          => now()->subDays(3),
        ]);

        $newer = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'invoice_number'     => 'INV-NEW-'.Str::upper(Str::random(4)),
            'invoice_hash'       => hash('sha256', Str::random(16)),
            'invoice_counter'    => 2,
            'source_type'        => 'pos',
            'source_id'          => 0,
            'subtotal'           => 2,
            'tax_amount'         => 0,
            'total'              => 2,
            'paid_amount'        => 0,
            'due_amount'         => 2,
            'status'             => 'pending',
            'currency'           => 'SAR',
            'issued_at'          => now(),
        ]);

        $items = $this->actingAsUser($tenant['user'])
            ->getJson($this->feedUrl().'?'.http_build_query(array_merge($this->baseQuery(14), ['types' => ['invoice'], 'per_page' => 50])))
            ->json('data.items');

        $posNew = $posOld = null;
        foreach ($items as $i => $r) {
            if ($r['type'] !== 'invoice') {
                continue;
            }
            if ((int) $r['id'] === $newer->id) {
                $posNew = $i;
            }
            if ((int) $r['id'] === $older->id) {
                $posOld = $i;
            }
        }
        $this->assertNotNull($posNew);
        $this->assertNotNull($posOld);
        $this->assertLessThan($posOld, $posNew);
    }
}
