<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\UserStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyReportingPulseTest extends TestCase
{
    private function companyPulseUrl(): string
    {
        return '/api/v1/reporting/v1/company/pulse-summary';
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

    public function test_owner_receives_company_pulse_envelope(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->assertOk()
            ->assertJsonPath('report.id', 'company.pulse_summary')
            ->assertJsonPath('report.read_only', true)
            ->assertJsonPath('meta.financial_metrics_included', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'users_total',
                        'customers_total',
                        'branches_total',
                        'work_orders_in_period',
                        'invoices_in_period',
                        'payments_in_period',
                        'tickets_open',
                        'tickets_overdue',
                    ],
                    'breakdown' => [
                        'by_branch',
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

        $this->actingAsUser($tenant['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->assertForbidden();
    }

    public function test_users_total_unchanged_when_other_company_adds_user(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $before = (int) $this->actingAsUser($a['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->json('data.summary.users_total');

        User::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $b['company']->id,
            'branch_id'  => $b['branch']->id,
            'name'       => 'Other Co User',
            'email'      => 'extra_'.Str::random(8).'@other.sa',
            'password'   => bcrypt('Password123!'),
            'role'       => 'staff',
            'status'     => UserStatus::Active,
            'is_active'  => true,
        ]);

        $after = (int) $this->actingAsUser($a['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->json('data.summary.users_total');

        $this->assertSame($before, $after);
    }

    public function test_staff_without_cross_branch_only_counts_own_branch_work_orders(): void
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
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->json('data.summary.work_orders_in_period');

        $this->assertSame(1, $n);
    }

    public function test_platform_email_owner_can_access_company_pulse(): void
    {
        Config::set('saas.platform_admin_emails', ['platform-lead@test.sa']);

        $tenant = $this->createTenant('owner');
        $tenant['user']->update(['email' => 'platform-lead@test.sa']);

        $this->actingAsUser($tenant['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->assertOk()
            ->assertJsonPath('report.id', 'company.pulse_summary');
    }

    public function test_viewer_excludes_financial_metrics(): void
    {
        $tenant = $this->createTenant('viewer');

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()));

        $res->assertOk()
            ->assertJsonPath('meta.financial_metrics_included', false)
            ->assertJsonPath('data.summary.invoices_in_period', 0)
            ->assertJsonPath('data.summary.payments_in_period', 0);
    }

    public function test_owner_sees_invoice_in_period_when_financial_allowed(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id'  => $tenant['branch']->id,
            'type'       => 'b2c',
            'name'       => 'Inv Cust',
            'is_active'  => true,
        ]);

        Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $tenant['company']->id,
            'branch_id'          => $tenant['branch']->id,
            'created_by_user_id' => $tenant['user']->id,
            'customer_id'        => $customer->id,
            'invoice_number'     => 'INV-PR9-'.Str::upper(Str::random(4)),
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

        $n = (int) $this->actingAsUser($tenant['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($this->dateRange()))
            ->json('data.summary.invoices_in_period');

        $this->assertGreaterThanOrEqual(1, $n);
    }

    public function test_invalid_branch_returns_422(): void
    {
        $tenant = $this->createTenant('owner');

        $q = array_merge($this->dateRange(), ['branch_id' => 999999999]);

        $this->actingAsUser($tenant['user'])
            ->getJson($this->companyPulseUrl().'?'.http_build_query($q))
            ->assertStatus(422);
    }
}
