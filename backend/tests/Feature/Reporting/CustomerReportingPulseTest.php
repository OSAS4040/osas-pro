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

class CustomerReportingPulseTest extends TestCase
{
    private function customerPulseUrl(): string
    {
        return '/api/v1/reporting/v1/customer/pulse-summary';
    }

    /**
     * @return array{from: string, to: string}
     */
    private function dateRange(): array
    {
        return [
            'from' => now()->subDays(7)->toDateString(),
            'to'   => now()->addDay()->toDateString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function queryWithCustomer(int $customerId, array $extra = []): string
    {
        return $this->customerPulseUrl().'?'.http_build_query(array_merge($this->dateRange(), ['customer_id' => $customerId], $extra));
    }

    public function test_owner_receives_customer_pulse_envelope(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Pulse Cust',
            'is_active'  => true,
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson($this->queryWithCustomer($customer->id))
            ->assertOk()
            ->assertJsonPath('report.id', 'customer.pulse_summary')
            ->assertJsonPath('report.read_only', true)
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonPath('meta.financial_metrics_included', true)
            ->assertJsonPath('meta.filters_applied.customer_id', $customer->id)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'work_orders_in_period',
                        'invoices_in_period',
                        'payments_in_period',
                        'tickets_open',
                        'tickets_overdue',
                        'last_activity_at',
                        'vehicles_count',
                    ],
                    'breakdown' => [
                        'by_status',
                        'by_activity',
                        'by_time_period',
                    ],
                ],
            ]);
    }

    public function test_technician_without_report_permissions_forbidden(): void
    {
        $tenant = $this->createTenant('technician');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'T Cust',
            'is_active'  => true,
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson($this->queryWithCustomer($customer->id))
            ->assertForbidden();
    }

    public function test_customer_from_other_company_returns_422(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $foreign = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $b['company']->id,
            'branch_id'  => $b['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Foreign',
            'is_active'  => true,
        ]);

        $this->actingAsUser($a['user'])
            ->getJson($this->queryWithCustomer($foreign->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid customer for this company.');
    }

    public function test_missing_customer_id_returns_422_validation(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->getJson($this->customerPulseUrl().'?'.http_build_query($this->dateRange()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_staff_without_cross_branch_only_counts_own_branch_work_orders_for_customer(): void
    {
        $company = $this->createCompany();
        $b1 = $this->createBranch($company, ['name' => 'B1', 'code' => 'B1', 'is_main' => true]);
        $b2 = $this->createBranch($company, ['name' => 'B2', 'code' => 'B2', 'is_main' => false]);
        $this->createActiveSubscription($company);

        $staff = $this->createUser($company, $b1, 'staff');

        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id'  => $b1->id,
            'type'       => 'b2c',
            'name'       => 'C',
            'is_active'  => true,
        ]);

        $v1 = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $b1->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number'       => 'P1-'.Str::upper(Str::random(4)),
            'make'               => 'A',
            'model'              => 'B',
            'year'               => 2023,
            'is_active'          => true,
        ]);

        $v2 = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $b2->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $staff->id,
            'plate_number'       => 'P2-'.Str::upper(Str::random(4)),
            'make'               => 'A',
            'model'              => 'B',
            'year'               => 2023,
            'is_active'          => true,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $b1->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $v1->id,
            'created_by_user_id' => $staff->id,
            'order_number'       => 'WO-C1-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        WorkOrder::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $b2->id,
            'customer_id'        => $customer->id,
            'vehicle_id'         => $v2->id,
            'created_by_user_id' => $staff->id,
            'order_number'       => 'WO-C2-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::Draft,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $n = (int) $this->actingAsUser($staff)
            ->getJson($this->queryWithCustomer($customer->id))
            ->json('data.summary.work_orders_in_period');

        $this->assertSame(1, $n);

        $vehicles = (int) $this->actingAsUser($staff)
            ->getJson($this->queryWithCustomer($customer->id))
            ->json('data.summary.vehicles_count');

        $this->assertSame(1, $vehicles);
    }

    public function test_viewer_excludes_financial_metrics(): void
    {
        $tenant = $this->createTenant('viewer');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'V Cust',
            'is_active'  => true,
        ]);

        Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'invoice_number'     => 'INV-PR10-'.Str::upper(Str::random(4)),
            'invoice_hash'       => hash('sha256', Str::random(16)),
            'invoice_counter'    => 1,
            'source_type'        => 'pos',
            'source_id'          => 0,
            'subtotal'           => 100,
            'tax_amount'         => 15,
            'total'              => 115,
            'paid_amount'        => 0,
            'due_amount'         => 115,
            'status'             => 'pending',
            'currency'           => 'SAR',
            'issued_at'          => now(),
        ]);

        $this->actingAsUser($tenant['user'])
            ->getJson($this->queryWithCustomer($customer->id))
            ->assertOk()
            ->assertJsonPath('meta.financial_metrics_included', false)
            ->assertJsonPath('data.summary.invoices_in_period', 0)
            ->assertJsonPath('data.summary.payments_in_period', 0)
            ->assertJsonPath('data.breakdown.by_time_period.invoices', []);
    }

    public function test_owner_counts_invoice_and_payment_in_period(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Pay Cust',
            'is_active'  => true,
        ]);

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'invoice_number'     => 'INV-PR10P-'.Str::upper(Str::random(4)),
            'invoice_hash'       => hash('sha256', Str::random(16)),
            'invoice_counter'    => 1,
            'source_type'        => 'pos',
            'source_id'          => 0,
            'subtotal'           => 50,
            'tax_amount'         => 0,
            'total'              => 50,
            'paid_amount'        => 0,
            'due_amount'         => 50,
            'status'             => 'pending',
            'currency'           => 'SAR',
            'issued_at'          => now(),
        ]);

        Payment::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'invoice_id'         => $invoice->id,
            'created_by_user_id' => $tenant['user']->id,
            'method'             => 'cash',
            'amount'             => 50,
            'currency'           => 'SAR',
            'status'             => 'completed',
            'trace_id'           => (string) Str::uuid(),
            'created_at'         => now(),
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->queryWithCustomer($customer->id));

        $res->assertOk()
            ->assertJsonPath('data.summary.invoices_in_period', 1)
            ->assertJsonPath('data.summary.payments_in_period', 1)
            ->assertJsonPath('data.breakdown.by_activity.invoices_issued_in_period', 1)
            ->assertJsonPath('data.breakdown.by_activity.payments_recorded_in_period', 1);
    }

    public function test_summary_work_order_count_matches_created_in_period(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'WO Cust',
            'is_active'  => true,
        ]);

        $vehicle = Vehicle::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'customer_id'        => $customer->id,
            'created_by_user_id' => $tenant['user']->id,
            'plate_number'       => 'WO-'.Str::upper(Str::random(4)),
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
            'order_number'       => 'WO-PR10-'.Str::upper(Str::random(4)),
            'status'             => WorkOrderStatus::InProgress,
            'priority'           => 'normal',
            'estimated_total'    => 0,
            'actual_total'       => 0,
            'version'            => 0,
        ]);

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->queryWithCustomer($customer->id));

        $res->assertOk();
        $wo = (int) $res->json('data.summary.work_orders_in_period');
        $act = (int) $res->json('data.breakdown.by_activity.work_orders_created_in_period');
        $this->assertSame(1, $wo);
        $this->assertSame(1, $act);
    }
}
