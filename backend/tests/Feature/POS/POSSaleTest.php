<?php

namespace Tests\Feature\POS;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class POSSaleTest extends TestCase
{
    use RefreshDatabase;

    private Company  $company;
    private Branch   $branch;
    private User     $user;
    private Customer $customer;
    private Product  $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company  = $this->createCompany();
        $this->branch   = $this->createBranch($this->company);
        $this->user     = $this->createUser($this->company, $this->branch);
        $this->createActiveSubscription($this->company);

        $this->customer = Customer::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $this->company->id,
            'created_by_user_id' => $this->user->id,
            'name'               => 'Walk-In Customer',
            'customer_type'      => 'individual',
            'is_active'          => true,
        ]);

        $unit = Unit::create([
            'company_id' => $this->company->id,
            'name'       => 'Piece', 'symbol' => 'pcs',
            'type'       => 'quantity', 'is_base' => true,
            'is_system'  => false, 'is_active' => true,
        ]);

        $this->product = Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $this->company->id,
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
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  100,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'setup',
        );
    }

    private function salePayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id'  => $this->customer->id,
            'customer_type' => 'b2c',
            'items' => [
                [
                    'name'       => $this->product->name,
                    'product_id' => $this->product->id,
                    'quantity'   => 2,
                    'unit_price' => 50.00,
                    'cost_price' => 30.00,
                    'tax_rate'   => 15,
                ],
            ],
            'payment' => ['method' => 'cash', 'amount' => 115.00],
        ], $overrides);
    }

    public function test_b2c_sale_creates_invoice_payment_and_deducts_stock(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'paid');
        $response->assertJsonPath('data.due_amount', '0.0000');

        $invoice = Invoice::find($response->json('data.id'));
        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertEquals(100.0, (float) $invoice->subtotal);
        $this->assertEquals(15.0, (float) $invoice->tax_amount);
        $this->assertEquals(115.0, (float) $invoice->total);

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertEquals(98, $inventory->quantity);
    }

    public function test_invoice_hash_is_populated(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $response->assertStatus(201);

        $invoice = Invoice::find($response->json('data.id'));
        $this->assertNotEmpty($invoice->invoice_hash);
        $this->assertNotEmpty($invoice->previous_invoice_hash);
        $this->assertEquals(64, strlen($invoice->invoice_hash));
    }

    public function test_invoice_counter_is_sequential(): void
    {
        $r1 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r2 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $c1 = Invoice::find($r1->json('data.id'))->invoice_counter;
        $c2 = Invoice::find($r2->json('data.id'))->invoice_counter;

        $this->assertEquals(1, $c2 - $c1);
    }

    public function test_invoice_numbers_are_unique_per_company(): void
    {
        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $r = $this->actingAs($this->user, 'sanctum')
                ->withHeaders(['Idempotency-Key' => Str::uuid()])
                ->postJson('/api/v1/pos/sale', $this->salePayload());

            $numbers[] = $r->json('data.invoice_number');
        }

        $this->assertCount(3, array_unique($numbers), 'Invoice numbers must be unique');
    }

    public function test_sale_fails_without_idempotency_key(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Idempotency-Key header is required.']);
    }

    public function test_sale_fails_when_insufficient_stock(): void
    {
        $payload = $this->salePayload([
            'items' => [[
                'name'       => $this->product->name,
                'product_id' => $this->product->id,
                'quantity'   => 999,
                'unit_price' => 50.00,
                'tax_rate'   => 15,
            ]],
            'payment' => ['method' => 'cash', 'amount' => 57442.50],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $payload);

        $response->assertStatus(422);
    }

    public function test_second_hash_references_first_invoice_hash(): void
    {
        $r1 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r2 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $inv1 = Invoice::find($r1->json('data.id'));
        $inv2 = Invoice::find($r2->json('data.id'));

        $this->assertEquals($inv1->invoice_hash, $inv2->previous_invoice_hash);
    }

    public function test_pos_rejects_payment_above_invoice_total(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload([
                'payment' => ['method' => 'cash', 'amount' => 999.99],
            ]));

        $response->assertStatus(422);
        $this->assertStringContainsString('exceeds invoice due amount', (string) $response->json('message'));
    }
}
