<?php

namespace Tests\Feature\WorkOrder;

use App\Models\Branch;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BundleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Branch $branch;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->createCompany();
        $this->branch  = $this->createBranch($this->company);
        $this->user    = $this->createUser($this->company, $this->branch);
        $this->createActiveSubscription($this->company);
    }

    private function createService(float $price): Service
    {
        return Service::create([
            'company_id'         => $this->company->id,
            'created_by_user_id' => $this->user->id,
            'name'               => 'Service ' . Str::random(4),
            'base_price'         => $price,
            'tax_rate'           => 15,
            'is_active'          => true,
        ]);
    }

    private function createProduct(float $price): Product
    {
        $unit = Unit::create([
            'company_id' => $this->company->id,
            'name' => 'Piece', 'symbol' => 'pcs',
            'type' => 'quantity', 'is_base' => true,
            'is_system' => false, 'is_active' => true,
        ]);

        return Product::create([
            'uuid'            => Str::uuid(),
            'company_id'      => $this->company->id,
            'name'            => 'Product ' . Str::random(4),
            'sku'             => 'SKU-' . Str::random(5),
            'product_type'    => 'physical',
            'unit_id'         => $unit->id,
            'sale_price'      => $price,
            'track_inventory' => true,
            'is_active'       => true,
        ]);
    }

    public function test_bundle_calculates_total_from_items(): void
    {
        $svc1    = $this->createService(100);
        $svc2    = $this->createService(50);
        $product = $this->createProduct(30);

        $bundle = Bundle::create([
            'company_id'           => $this->company->id,
            'name'                 => 'Test Bundle',
            'override_item_prices' => false,
            'base_price'           => 0,
            'is_active'            => true,
        ]);

        BundleItem::create(['bundle_id' => $bundle->id, 'item_type' => 'service', 'service_id' => $svc1->id, 'quantity' => 1]);
        BundleItem::create(['bundle_id' => $bundle->id, 'item_type' => 'service', 'service_id' => $svc2->id, 'quantity' => 2]);
        BundleItem::create(['bundle_id' => $bundle->id, 'item_type' => 'product', 'product_id' => $product->id, 'quantity' => 3]);

        $bundle->load('items.service', 'items.product');

        $total = $bundle->calculateTotal();

        $this->assertEquals(100 * 1 + 50 * 2 + 30 * 3, $total);
    }

    public function test_bundle_uses_base_price_when_override_enabled(): void
    {
        $svc = $this->createService(100);

        $bundle = Bundle::create([
            'company_id'           => $this->company->id,
            'name'                 => 'Fixed Bundle',
            'override_item_prices' => true,
            'base_price'           => 199.99,
            'is_active'            => true,
        ]);

        BundleItem::create(['bundle_id' => $bundle->id, 'item_type' => 'service', 'service_id' => $svc->id, 'quantity' => 1]);

        $bundle->load('items.service');

        $this->assertEquals(199.99, $bundle->calculateTotal());
    }

    public function test_bundle_item_uses_price_override_when_set(): void
    {
        $svc = $this->createService(100);

        $bundle = Bundle::create([
            'company_id'           => $this->company->id,
            'name'                 => 'Override Bundle',
            'override_item_prices' => false,
            'base_price'           => 0,
            'is_active'            => true,
        ]);

        BundleItem::create([
            'bundle_id'           => $bundle->id,
            'item_type'           => 'service',
            'service_id'          => $svc->id,
            'quantity'            => 2,
            'unit_price_override' => 70.00,
        ]);

        $bundle->load('items.service');

        $this->assertEquals(140.0, $bundle->calculateTotal());
    }

    public function test_bundle_api_store_and_retrieve(): void
    {
        $svc = $this->createService(80);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/bundles', [
                'name'  => 'Oil Change Bundle',
                'items' => [
                    ['item_type' => 'service', 'service_id' => $svc->id, 'quantity' => 1],
                ],
            ]);

        $response->assertStatus(201);
        $id = $response->json('data.id');

        $show = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/bundles/{$id}");

        $show->assertStatus(200);
        $show->assertJsonPath('data.name', 'Oil Change Bundle');
        $show->assertJsonCount(1, 'data.items');
    }

    public function test_service_api_crud(): void
    {
        $store = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/services', [
                'name'       => 'Engine Flush',
                'base_price' => 150,
                'tax_rate'   => 15,
            ]);

        $store->assertStatus(201);
        $id = $store->json('data.id');

        $update = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/services/{$id}", ['base_price' => 175]);

        $update->assertStatus(200);
        $update->assertJsonPath('data.base_price', '175.0000');

        $delete = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/services/{$id}");

        $delete->assertStatus(200);
    }
}
