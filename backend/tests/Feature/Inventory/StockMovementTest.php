<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private User $user;
    private Product $product;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createCompany();
        $this->branch  = $this->createBranch($this->company);
        $this->user    = $this->createUser($this->company, $this->branch);
        $this->unit    = $this->createUnit($this->company);
        $this->product = $this->createProduct($this->company, $this->unit);
    }

    private function createUnit(Company $company): Unit
    {
        return Unit::create([
            'company_id' => $company->id,
            'name'       => 'Piece',
            'symbol'     => 'pcs',
            'type'       => 'quantity',
            'is_base'    => true,
            'is_system'  => false,
            'is_active'  => true,
        ]);
    }

    private function createProduct(Company $company, Unit $unit): Product
    {
        return Product::create([
            'uuid'               => Str::uuid(),
            'company_id'         => $company->id,
            'created_by_user_id' => null,
            'name'               => 'Test Product',
            'sku'                => 'SKU-' . Str::random(6),
            'product_type'       => 'physical',
            'unit_id'            => $unit->id,
            'sale_price'         => 10.00,
            'cost_price'         => 5.00,
            'track_inventory'    => true,
            'is_active'          => true,
        ]);
    }

    public function test_add_stock_creates_movement_and_updates_inventory(): void
    {
        $service = app(InventoryService::class);

        $movement = $service->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  10,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'trace-001',
        );

        $this->assertInstanceOf(StockMovement::class, $movement);
        $this->assertEquals(10, $movement->quantity);

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertNotNull($inventory);
        $this->assertEquals(10, $inventory->quantity);
    }

    public function test_deduct_stock_reduces_inventory(): void
    {
        $service = app(InventoryService::class);

        $service->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  20,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'trace-002',
        );

        $service->deductStock(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      7,
            userId:        $this->user->id,
            referenceType: 'test',
            referenceId:   1,
            traceId:       'trace-003',
        );

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertEquals(13, $inventory->quantity);
    }

    public function test_deduct_below_zero_throws_exception(): void
    {
        $service = app(InventoryService::class);

        $service->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  5,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'trace-004',
        );

        $this->expectException(\DomainException::class);

        $service->deductStock(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      10,
            userId:        $this->user->id,
            referenceType: 'test',
            referenceId:   1,
            traceId:       'trace-005',
        );
    }

    public function test_stock_movement_is_append_only(): void
    {
        $service = app(InventoryService::class);
        $movement = $service->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  5,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'trace-006',
        );

        $this->expectException(\RuntimeException::class);
        $movement->update(['quantity' => 99]);
    }

    public function test_stock_movement_delete_is_blocked(): void
    {
        $service = app(InventoryService::class);
        $movement = $service->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  5,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'trace-007',
        );

        $this->expectException(\RuntimeException::class);
        $movement->delete();
    }

    public function test_multiple_movements_sum_to_correct_balance(): void
    {
        $service = app(InventoryService::class);

        $service->addStock($this->company->id, $this->branch->id, $this->product->id, 100, $this->user->id, 'purchase_receipt', 'trace-008');
        $service->deductStock($this->company->id, $this->branch->id, $this->product->id, 30, $this->user->id, 'test', 1, 'trace-009');
        $service->addStock($this->company->id, $this->branch->id, $this->product->id, 20, $this->user->id, 'manual_add', 'trace-010');
        $service->deductStock($this->company->id, $this->branch->id, $this->product->id, 10, $this->user->id, 'test', 2, 'trace-011');

        $level = $service->getStockLevel($this->company->id, $this->branch->id, $this->product->id);

        $this->assertEquals(80, $level['quantity']);
    }
}
