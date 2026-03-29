<?php

namespace Tests\Feature\POS;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotencyTest extends TestCase
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
            'name'               => 'Idempotency Test Customer',
            'customer_type'      => 'individual',
            'is_active'          => true,
        ]);

        $unit = Unit::create([
            'company_id' => $this->company->id,
            'name' => 'Piece', 'symbol' => 'pcs',
            'type' => 'quantity', 'is_base' => true,
            'is_system' => false, 'is_active' => true,
        ]);

        $this->product = Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $this->company->id,
            'name'            => 'Filter',
            'sku'             => 'FLT-001',
            'product_type'    => 'physical',
            'unit_id'         => $unit->id,
            'sale_price'      => 20.00,
            'cost_price'      => 10.00,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        app(InventoryService::class)->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  50,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'setup',
        );
    }

    private function salePayload(): array
    {
        return [
            'customer_id'   => $this->customer->id,
            'customer_type' => 'b2c',
            'items' => [[
                'name'       => $this->product->name,
                'product_id' => $this->product->id,
                'quantity'   => 1,
                'unit_price' => 20.00,
                'tax_rate'   => 15,
            ]],
            'payment' => ['method' => 'cash', 'amount' => 23.00],
        ];
    }

    public function test_duplicate_request_with_same_key_returns_cached_invoice(): void
    {
        $key = Str::uuid();

        $r1 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r2 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r1->assertStatus(201);
        $r2->assertStatus(201);

        $this->assertEquals(
            $r1->json('data.id'),
            $r2->json('data.id'),
            'Duplicate request must return the same invoice'
        );

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_same_key_different_payload_returns_409(): void
    {
        $key = Str::uuid();

        $r1 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r1->assertStatus(201);

        $differentPayload = $this->salePayload();
        $differentPayload['items'][0]['quantity'] = 3;

        $r2 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/pos/sale', $differentPayload);

        $r2->assertStatus(409);
    }

    public function test_idempotency_service_stores_and_retrieves(): void
    {
        $service     = app(IdempotencyService::class);
        $companyId   = $this->company->id;
        $key         = 'test-key-' . Str::random(8);
        $endpoint    = 'test.endpoint';
        $payload     = ['amount' => 100, 'product_id' => 5];
        $hash        = $service->hashPayload($payload);

        $cached = $service->check($companyId, $key, $endpoint, $hash);
        $this->assertNull($cached, 'No cached result expected before store');

        $service->store($companyId, $key, $endpoint, $hash, ['invoice_id' => 99]);

        $cached = $service->check($companyId, $key, $endpoint, $hash);
        $this->assertNotNull($cached);
        $this->assertEquals(99, $cached['invoice_id']);
    }

    public function test_idempotency_service_throws_on_payload_mismatch(): void
    {
        $service   = app(IdempotencyService::class);
        $companyId = $this->company->id;
        $key       = 'mismatch-key-' . Str::random(8);
        $endpoint  = 'test.endpoint';

        $hash1 = $service->hashPayload(['amount' => 100]);
        $service->store($companyId, $key, $endpoint, $hash1, ['invoice_id' => 1]);

        $hash2 = $service->hashPayload(['amount' => 200]);

        $this->expectException(\DomainException::class);
        $service->check($companyId, $key, $endpoint, $hash2);
    }

    public function test_two_different_keys_create_two_invoices(): void
    {
        $r1 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r2 = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => Str::uuid()])
            ->postJson('/api/v1/pos/sale', $this->salePayload());

        $r1->assertStatus(201);
        $r2->assertStatus(201);

        $this->assertNotEquals($r1->json('data.id'), $r2->json('data.id'));
        $this->assertDatabaseCount('invoices', 2);
    }
}
