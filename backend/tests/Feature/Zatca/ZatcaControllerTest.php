<?php

namespace Tests\Feature\Zatca;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\ZatcaLog;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZatcaControllerTest extends TestCase
{
    public function test_status_reports_simulation_and_no_false_compliance_flags(): void
    {
        config(['zatca.simulation_mode' => true]);

        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->createActiveSubscription($company);

        $response = $this->actingAsUser($user)->getJson('/api/v1/zatca/status');

        $response->assertOk();
        $response->assertJsonPath('data.simulation_mode', true);
        $response->assertJsonPath('data.integration_active', false);
        $response->assertJsonPath('data.phase2_active', false);
        $response->assertJsonPath('data.csid_valid', false);
        $response->assertJsonPath('data.cr_valid', false);
        $response->assertJsonPath('data.last_sync', null);
    }

    public function test_submit_simulation_creates_zatca_log_row(): void
    {
        config(['zatca.simulation_mode' => true]);

        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->createActiveSubscription($company);

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $branch->id,
            'customer_id'        => null,
            'created_by_user_id' => $user->id,
            'invoice_number'     => 'INV-ZATCA-'.Str::upper(Str::random(6)),
            'type'               => 'sale',
            'status'             => InvoiceStatus::Pending,
            'customer_type'      => 'b2c',
            'subtotal'           => 100,
            'discount_amount'    => 0,
            'tax_amount'         => 15,
            'total'              => 115,
            'paid_amount'        => 0,
            'due_amount'         => 115,
            'currency'           => 'SAR',
            'invoice_hash'       => 'testhash',
        ]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/zatca/submit', [
            'invoice_id' => $invoice->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.simulation_mode', true);

        $this->assertDatabaseHas('zatca_logs', [
            'company_id'     => $company->id,
            'reference_type' => Invoice::class,
            'reference_id'   => $invoice->id,
            'action'         => 'clearance_simulation',
            'status'         => 'simulated',
        ]);
    }

    public function test_submit_returns_501_when_simulation_disabled_without_production_integration(): void
    {
        config(['zatca.simulation_mode' => false]);

        ['company' => $company, 'branch' => $branch, 'user' => $user] = $this->createTenant();
        $this->createActiveSubscription($company);

        $invoice = Invoice::create([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => $company->id,
            'branch_id'          => $branch->id,
            'customer_id'        => null,
            'created_by_user_id' => $user->id,
            'invoice_number'     => 'INV-ZATCA-'.Str::upper(Str::random(6)),
            'type'               => 'sale',
            'status'             => InvoiceStatus::Pending,
            'customer_type'      => 'b2c',
            'subtotal'           => 10,
            'discount_amount'    => 0,
            'tax_amount'         => 0,
            'total'              => 10,
            'paid_amount'        => 0,
            'due_amount'         => 10,
            'currency'           => 'SAR',
        ]);

        $before = ZatcaLog::count();

        $response = $this->actingAsUser($user)->postJson('/api/v1/zatca/submit', [
            'invoice_id' => $invoice->id,
        ]);

        $response->assertStatus(501);
        $response->assertJsonPath('code', 'ZATCA_NOT_CONFIGURED');
        $this->assertSame($before, ZatcaLog::count());
    }
}
