<?php

namespace Tests\Feature\Invoices;

use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutionPartnerBillingIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function enablePlatformExecutionPartner(Company $company): void
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $profile = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $matrix = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];
        $matrix['platform_execution_partner'] = true;
        $profile['feature_matrix'] = $matrix;
        $profile['business_type'] = $profile['business_type'] ?? 'service_center';
        $settings['business_profile'] = $profile;
        $company->update(['settings' => $settings]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseInvoiceRow(Company $c, Branch $b, User $u, string $number, ?string $billingFlow, ?int $customerId): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'company_id' => $c->id,
            'branch_id' => $b->id,
            'customer_id' => $customerId,
            'created_by_user_id' => $u->id,
            'invoice_number' => $number,
            'status' => 'pending',
            'type' => 'sale',
            'customer_type' => 'b2b',
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
            'paid_amount' => 0,
            'due_amount' => 115,
            'currency' => 'SAR',
            'issued_at' => now(),
            'trace_id' => 'trace-exec-partner',
            'billing_flow_type' => $billingFlow,
            'customer_visible' => true,
        ];
    }

    public function test_workshop_partner_hides_platform_to_customer_from_list_and_show(): void
    {
        if (! Schema::hasColumn('invoices', 'billing_flow_type')) {
            $this->markTestSkipped('invoices.billing_flow_type column required (run billing_flow migrations).');
        }

        $t = $this->createTenant();
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $t['company']->id,
            'branch_id' => $t['branch']->id,
            'type' => 'fleet',
            'name' => 'Exec Partner Cust',
            'is_active' => true,
        ]);

        $platformToCustomer = Invoice::create($this->baseInvoiceRow(
            $t['company'],
            $t['branch'],
            $t['user'],
            'INV-PTC-'.Str::upper(Str::random(4)),
            'platform_to_customer',
            $customer->id,
        ));

        $providerToPlatform = Invoice::create($this->baseInvoiceRow(
            $t['company'],
            $t['branch'],
            $t['user'],
            'INV-PTP-'.Str::upper(Str::random(4)),
            'provider_to_platform',
            null,
        ));

        $this->enablePlatformExecutionPartner($t['company']);

        $list = $this->actingAsUser($t['user'])->getJson('/api/v1/invoices?per_page=50');
        $list->assertOk();
        $ids = collect($list->json('data.data'))->pluck('id')->all();
        $this->assertContains($providerToPlatform->id, $ids);
        $this->assertNotContains($platformToCustomer->id, $ids);

        $this->actingAsUser($t['user'])
            ->getJson("/api/v1/invoices/{$platformToCustomer->id}")
            ->assertNotFound();
    }

    public function test_workshop_partner_cannot_create_invoice_or_from_work_order_via_api(): void
    {
        $t = $this->createTenant();
        $this->enablePlatformExecutionPartner($t['company']);

        $this->actingAsUser($t['user'])
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/invoices', [
                'items' => [[
                    'name' => 'Line',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'tax_rate' => 15,
                ]],
            ])
            ->assertStatus(403);

        $this->actingAsUser($t['user'])
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/invoices/from-work-order/999999')
            ->assertStatus(403);
    }

    public function test_fleet_user_does_not_see_platform_to_provider_purchase(): void
    {
        if (! Schema::hasColumn('purchases', 'billing_flow_type')) {
            $this->markTestSkipped('purchases.billing_flow_type column required (run billing_flow migrations).');
        }

        $t = $this->createTenant();
        $customer = Customer::create([
            'uuid' => Str::uuid(),
            'company_id' => $t['company']->id,
            'branch_id' => $t['branch']->id,
            'type' => 'fleet',
            'name' => 'Fleet PO Cust',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'uuid' => Str::uuid(),
            'company_id' => $t['company']->id,
            'created_by_user_id' => $t['user']->id,
            'name' => 'Settlement Supplier',
            'is_active' => true,
            'status' => 'active',
        ]);

        $settlementPo = Purchase::create([
            'uuid' => Str::uuid(),
            'company_id' => $t['company']->id,
            'branch_id' => $t['branch']->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $t['user']->id,
            'reference_number' => 'PO-SET-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Ordered,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 50,
            'tax_amount' => 7.5,
            'total' => 57.5,
            'currency' => 'SAR',
            'trace_id' => 'trace-settlement-po',
        ]);

        $normalPo = Purchase::create([
            'uuid' => Str::uuid(),
            'company_id' => $t['company']->id,
            'branch_id' => $t['branch']->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $t['user']->id,
            'reference_number' => 'PO-NRM-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Ordered,
            'billing_flow_type' => null,
            'subtotal' => 10,
            'tax_amount' => 1.5,
            'total' => 11.5,
            'currency' => 'SAR',
            'trace_id' => 'trace-normal-po',
        ]);

        $fleetManager = $this->createUser($t['company'], $t['branch'], 'fleet_manager', [
            'customer_id' => $customer->id,
        ]);

        $list = $this->actingAsUser($fleetManager)->getJson('/api/v1/purchases?per_page=50');
        $list->assertOk();
        $ids = collect($list->json('data.data'))->pluck('id')->all();
        $this->assertContains($normalPo->id, $ids);
        $this->assertNotContains($settlementPo->id, $ids);

        $this->actingAsUser($fleetManager)
            ->getJson("/api/v1/purchases/{$settlementPo->id}")
            ->assertNotFound();
    }
}
