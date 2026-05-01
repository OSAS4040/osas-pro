<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Models\Customer;
use App\Services\InvoiceService;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ReportSalesExcludesInternalSettlementTest extends TestCase
{
    public function test_sales_summary_omits_provider_to_platform_invoices_from_totals(): void
    {
        $tenant = $this->createTenant('owner');
        $company = $tenant['company'];
        $branch = $tenant['branch'];
        $owner = $tenant['user'];

        $customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'type' => 'b2b',
            'name' => 'Report Rev Customer',
            'is_active' => true,
        ]);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);

        $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'provider_to_platform',
            'customer_visible' => false,
            'customer_type' => 'b2b',
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Internal leg',
                'quantity' => 1,
                'unit_price' => 500,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $invoiceService->createInvoice([
            'type' => 'sale',
            'billing_flow_type' => 'platform_to_customer',
            'customer_visible' => true,
            'customer_id' => $customer->id,
            'customer_type' => 'b2b',
            'currency' => 'SAR',
            'items' => [[
                'name' => 'Customer leg',
                'quantity' => 1,
                'unit_price' => 500,
                'tax_rate' => 15,
            ]],
            'idempotency_key' => (string) Str::uuid(),
        ], (int) $company->id, (int) $branch->id, (int) $owner->id);

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $res = $this->actingAsUser($owner)->getJson('/api/v1/reports/sales?from='.$from.'&to='.$to);
        $res->assertOk();

        $this->assertSame(1, (int) $res->json('data.summary.invoice_count'));
        $this->assertEqualsWithDelta(575.0, (float) $res->json('data.summary.total_sales'), 0.02);
    }
}
