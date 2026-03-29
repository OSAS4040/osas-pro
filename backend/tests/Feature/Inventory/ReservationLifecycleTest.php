<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private User $user;
    private Product $product;
    private Unit $unit;
    private InventoryService $inventoryService;
    private ReservationService $reservationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company  = $this->createCompany();
        $this->branch   = $this->createBranch($this->company);
        $this->user     = $this->createUser($this->company, $this->branch);
        $this->unit     = Unit::create([
            'company_id' => $this->company->id,
            'name'       => 'Piece', 'symbol' => 'pcs',
            'type'       => 'quantity', 'is_base' => true,
            'is_system'  => false, 'is_active' => true,
        ]);
        $this->product  = Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $this->company->id,
            'name'            => 'Test Part',
            'sku'             => 'PART-001',
            'product_type'    => 'physical',
            'unit_id'         => $this->unit->id,
            'sale_price'      => 50.00,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        $this->inventoryService   = app(InventoryService::class);
        $this->reservationService = app(ReservationService::class);

        $this->inventoryService->addStock(
            companyId: $this->company->id,
            branchId:  $this->branch->id,
            productId: $this->product->id,
            quantity:  100,
            userId:    $this->user->id,
            type:      'manual_add',
            traceId:   'setup',
        );
    }

    public function test_reserve_creates_pending_reservation(): void
    {
        $reservation = $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      10,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   1,
            traceId:       'trace-res-001',
        );

        $this->assertEquals('pending', $reservation->status);
        $this->assertEquals(10, $reservation->quantity);

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertEquals(10, $inventory->reserved_quantity);
    }

    public function test_consume_reservation_deducts_stock(): void
    {
        $reservation = $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      15,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   1,
            traceId:       'trace-res-002',
        );

        $this->reservationService->consume($reservation, 'trace-res-003');

        $reservation->refresh();
        $this->assertEquals('consumed', $reservation->status);

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertEquals(85, $inventory->quantity);
        $this->assertEquals(0, $inventory->reserved_quantity);
    }

    public function test_release_reservation_frees_reserved_quantity(): void
    {
        $reservation = $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      20,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   2,
            traceId:       'trace-res-004',
        );

        $this->reservationService->release($reservation);

        $reservation->refresh();
        $this->assertEquals('released', $reservation->status);

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertEquals(100, $inventory->quantity);
        $this->assertEquals(0, $inventory->reserved_quantity);
    }

    public function test_cancel_reservation_frees_reserved_quantity(): void
    {
        $reservation = $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      5,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   3,
            traceId:       'trace-res-005',
        );

        $this->reservationService->cancel($reservation);

        $reservation->refresh();
        $this->assertEquals('canceled', $reservation->status);

        $inventory = Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->first();

        $this->assertEquals(0, $inventory->reserved_quantity);
    }

    public function test_consumed_reservation_cannot_be_consumed_again(): void
    {
        $reservation = $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      5,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   4,
            traceId:       'trace-res-006',
        );

        $this->reservationService->consume($reservation, 'trace-res-007');
        $reservation->refresh();

        $this->expectException(\DomainException::class);
        $this->reservationService->consume($reservation, 'trace-res-008');
    }

    public function test_reserve_fails_when_insufficient_available_stock(): void
    {
        $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      90,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   5,
            traceId:       'trace-res-009',
        );

        $this->expectException(\DomainException::class);

        $this->reservationService->reserve(
            companyId:     $this->company->id,
            branchId:      $this->branch->id,
            productId:     $this->product->id,
            quantity:      20,
            userId:        $this->user->id,
            referenceType: 'work_order',
            referenceId:   6,
            traceId:       'trace-res-010',
        );
    }

    public function test_expire_overdue_updates_status(): void
    {
        $reservation = InventoryReservation::create([
            'company_id'         => $this->company->id,
            'branch_id'          => $this->branch->id,
            'product_id'         => $this->product->id,
            'created_by_user_id' => $this->user->id,
            'quantity'           => 5,
            'status'             => 'pending',
            'expires_at'         => now()->subHour(),
        ]);

        Inventory::where([
            'company_id' => $this->company->id,
            'branch_id'  => $this->branch->id,
            'product_id' => $this->product->id,
        ])->increment('reserved_quantity', 5);

        $count = $this->reservationService->expireOverdue();

        $this->assertGreaterThanOrEqual(1, $count);
        $reservation->refresh();
        $this->assertEquals('expired', $reservation->status);
    }
}
