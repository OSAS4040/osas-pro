<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\LedgerPostingFailedException;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Services\InventoryService;
use App\Services\InvoiceService;
use App\Services\LedgerService;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_04_PROGRESS_REPORT.md — محاسبة / قيود
 */
#[Group('phase4')]
class LedgerPostingRollbackTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        if ($this->app->bound(LedgerService::class)) {
            $this->app->forgetInstance(LedgerService::class);
        }
        parent::tearDown();
    }

    public function test_pos_sale_returns_503_and_rolls_back_when_ledger_post_fails(): void
    {
        $company  = $this->createCompany();
        $branch   = $this->createBranch($company);
        $user     = $this->createUser($company, $branch);
        $this->createActiveSubscription($company);

        $customer = Customer::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $company->id,
            'created_by_user_id' => $user->id,
            'name'               => 'Walk-In Customer',
            'customer_type'      => 'individual',
            'is_active'          => true,
        ]);

        $unit = Unit::create([
            'company_id' => $company->id,
            'name'       => 'Piece', 'symbol' => 'pcs',
            'type'       => 'quantity', 'is_base' => true,
            'is_system'  => false, 'is_active' => true,
        ]);

        $product = Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $company->id,
            'name'            => 'Engine Oil 5W30',
            'sku'             => 'OIL-5W30',
            'product_type'    => 'consumable',
            'unit_id'         => $unit->id,
            'sale_price'      => 50.00,
            'cost_price'      => 30.00,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        app(InventoryService::class)->addStock(
            companyId: $company->id,
            branchId:  $branch->id,
            productId: $product->id,
            quantity:  100,
            userId:    $user->id,
            type:      'manual_add',
            traceId:   'setup',
        );

        $mock = Mockery::mock(LedgerService::class);
        $mock->shouldReceive('post')->andThrow(new \RuntimeException('simulated ledger failure'));
        $this->app->instance(LedgerService::class, $mock);

        $beforeInvoices = Invoice::count();
        $beforeQty      = (float) Inventory::where([
            'company_id' => $company->id,
            'branch_id'  => $branch->id,
            'product_id' => $product->id,
        ])->value('quantity');

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', [
                'customer_id'   => $customer->id,
                'customer_type' => 'b2c',
                'items'         => [
                    [
                        'name'       => $product->name,
                        'product_id' => $product->id,
                        'quantity'   => 2,
                        'unit_price' => 50.00,
                        'cost_price' => 30.00,
                        'tax_rate'   => 15,
                    ],
                ],
                'payment' => ['method' => 'cash', 'amount' => 115.00],
            ]);

        $response->assertStatus(503);
        $response->assertHeader('Retry-After', '5');
        $response->assertJsonPath('code', LedgerPostingFailedException::ERROR_CODE);
        $response->assertJsonStructure(['message', 'code', 'trace_id']);

        $this->assertSame($beforeInvoices, Invoice::count());
        $afterQty = (float) Inventory::where([
            'company_id' => $company->id,
            'branch_id'  => $branch->id,
            'product_id' => $product->id,
        ])->value('quantity');
        $this->assertSame($beforeQty, $afterQty);
    }

    public function test_invoice_create_rolls_back_when_ledger_post_fails(): void
    {
        $tenant = $this->createTenant();

        $mock = Mockery::mock(LedgerService::class);
        $mock->shouldReceive('post')->andThrow(new \RuntimeException('simulated ledger failure'));
        $this->app->instance(LedgerService::class, $mock);

        $before = Invoice::where('company_id', $tenant['company']->id)->count();

        $caught = false;
        try {
            app(InvoiceService::class)->createInvoice(
                data: [
                    'customer_type' => 'b2c',
                    'items'         => [
                        ['name' => 'Line', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15],
                    ],
                ],
                companyId: $tenant['company']->id,
                branchId:  $tenant['branch']->id,
                userId:    $tenant['user']->id,
            );
        } catch (LedgerPostingFailedException $e) {
            $caught = true;
            $this->assertSame('invoice', $e->source);
        }

        $this->assertTrue($caught, 'Expected LedgerPostingFailedException');
        $this->assertSame($before, Invoice::where('company_id', $tenant['company']->id)->count());
    }

    public function test_ledger_posting_exception_report_runs_without_error(): void
    {
        $this->expectNotToPerformAssertions();
        $e = new LedgerPostingFailedException(
            source: 'invoice',
            companyId: 42,
            invoiceId: 99,
            previous: new \RuntimeException('inner'),
        );
        $e->report();
    }
}
