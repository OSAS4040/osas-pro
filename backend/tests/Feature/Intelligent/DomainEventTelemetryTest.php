<?php

namespace Tests\Feature\Intelligent;

use App\Models\DomainEvent;
use App\Models\Product;
use App\Models\Unit;
use App\Services\InventoryService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainEventTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private function enableEventPersistence(): void
    {
        config(['intelligent.events.enabled' => true]);
        config(['intelligent.events.persist.enabled' => true]);
    }

    public function test_customer_create_persists_domain_event_with_tenant_fields(): void
    {
        $this->enableEventPersistence();
        ['company' => $company, 'user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->postJson('/api/v1/customers', [
            'type' => 'b2c',
            'name' => 'Telemetry Customer',
        ])->assertCreated();

        $row = DomainEvent::where('event_name', 'CustomerCreated')
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('customer', $row->aggregate_type);
        $this->assertNotEmpty($row->aggregate_id);
        $this->assertIsArray($row->payload_json);
        $this->assertNotEmpty($row->trace_id);
    }

    public function test_add_stock_persists_stock_movement_recorded(): void
    {
        $this->enableEventPersistence();
        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $user    = $this->createUser($company, $branch);
        $unit    = Unit::create([
            'company_id' => $company->id,
            'name'       => 'Piece', 'symbol' => 'pcs',
            'type'       => 'quantity', 'is_base' => true,
            'is_system'  => false, 'is_active' => true,
        ]);
        $product = Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $company->id,
            'name'            => 'Stock Test SKU',
            'sku'             => 'STK-TEL-1',
            'product_type'    => 'physical',
            'unit_id'         => $unit->id,
            'sale_price'      => 10.00,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        $before = DomainEvent::count();
        app(InventoryService::class)->addStock(
            companyId: $company->id,
            branchId: $branch->id,
            productId: $product->id,
            quantity: 5,
            userId: $user->id,
            type: 'manual_add',
            traceId: 'tel-stock-1',
        );

        $this->assertSame($before + 1, DomainEvent::count());
        $ev = DomainEvent::where('event_name', 'StockMovementRecorded')->first();
        $this->assertNotNull($ev);
        $this->assertSame($company->id, (int) $ev->company_id);
        $this->assertSame('stock_movement', $ev->aggregate_type);
    }

    public function test_reserve_emits_inventory_reserved(): void
    {
        $this->enableEventPersistence();
        $company = $this->createCompany();
        $branch  = $this->createBranch($company);
        $user    = $this->createUser($company, $branch);
        $unit    = Unit::create([
            'company_id' => $company->id,
            'name'       => 'Piece', 'symbol' => 'pcs',
            'type'       => 'quantity', 'is_base' => true,
            'is_system'  => false, 'is_active' => true,
        ]);
        $product = Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $company->id,
            'name'            => 'Res Test',
            'sku'             => 'RES-TEL-1',
            'product_type'    => 'physical',
            'unit_id'         => $unit->id,
            'sale_price'      => 10.00,
            'track_inventory' => true,
            'is_active'       => true,
        ]);

        app(InventoryService::class)->addStock(
            companyId: $company->id,
            branchId: $branch->id,
            productId: $product->id,
            quantity: 20,
            userId: $user->id,
            type: 'manual_add',
            traceId: 'tel-res-setup',
        );

        DomainEvent::query()->delete();

        app(ReservationService::class)->reserve(
            companyId: $company->id,
            branchId: $branch->id,
            productId: $product->id,
            quantity: 3,
            userId: $user->id,
            referenceType: 'work_order',
            referenceId: 99,
            traceId: 'tel-res',
        );

        $this->assertSame(1, DomainEvent::where('event_name', 'InventoryReserved')->count());
        $ev = DomainEvent::where('event_name', 'InventoryReserved')->first();
        $this->assertSame('inventory_reservation', $ev->aggregate_type);
        $this->assertSame($company->id, (int) $ev->company_id);
    }

    public function test_insights_sees_persisted_events_when_phase2_enabled(): void
    {
        $this->enableEventPersistence();
        config(['intelligent.internal_dashboard.enabled' => true]);
        config(['intelligent.read_models.enabled' => true]);
        config(['intelligent.phase2.enabled' => true]);
        config(['intelligent.insights.enabled' => true]);
        config(['intelligent.phase2.features.insights' => true]);

        ['company' => $company, 'user' => $user] = $this->createTenant();

        $this->actingAsUser($user)->postJson('/api/v1/customers', [
            'type' => 'b2c',
            'name' => 'Insights Feed Customer',
        ])->assertCreated();

        $res = $this->actingAsUser($user)->getJson('/api/v1/internal/intelligence/insights');
        $res->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $res->json('data.totals.events'));
    }
}
