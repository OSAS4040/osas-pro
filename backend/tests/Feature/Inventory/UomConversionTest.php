<?php

namespace Tests\Feature\Inventory;

use App\Models\Company;
use App\Models\Unit;
use App\Models\UnitConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UomConversionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = $this->createCompany();
    }

    private function createUnit(string $symbol): Unit
    {
        return Unit::create([
            'company_id' => $this->company->id,
            'name'       => $symbol,
            'symbol'     => $symbol,
            'type'       => 'quantity',
            'is_base'    => false,
            'is_system'  => false,
            'is_active'  => true,
        ]);
    }

    public function test_convert_to_same_unit_returns_same_quantity(): void
    {
        $unit   = $this->createUnit('kg');
        $result = $unit->convertTo($unit, 10);

        $this->assertEquals(10.0, $result);
    }

    public function test_forward_conversion_applies_factor(): void
    {
        $kg = $this->createUnit('kg');
        $g  = $this->createUnit('g');

        UnitConversion::create([
            'company_id'   => $this->company->id,
            'from_unit_id' => $kg->id,
            'to_unit_id'   => $g->id,
            'factor'       => 1000,
            'is_active'    => true,
        ]);

        $result = $kg->convertTo($g, 2.5);

        $this->assertEquals(2500.0, $result);
    }

    public function test_reverse_conversion_uses_inverse_factor(): void
    {
        $kg = $this->createUnit('kg2');
        $g  = $this->createUnit('g2');

        UnitConversion::create([
            'company_id'   => $this->company->id,
            'from_unit_id' => $kg->id,
            'to_unit_id'   => $g->id,
            'factor'       => 1000,
            'is_active'    => true,
        ]);

        $result = $g->convertTo($kg, 500);

        $this->assertEquals(0.5, $result);
    }

    public function test_conversion_not_found_throws_exception(): void
    {
        $a = $this->createUnit('foo');
        $b = $this->createUnit('bar');

        $this->expectException(\DomainException::class);
        $a->convertTo($b, 5);
    }

    public function test_unit_conversion_api_store_and_list(): void
    {
        $branch = $this->createBranch($this->company);
        $user   = $this->createUser($this->company, $branch);
        $this->createActiveSubscription($this->company);

        $from = $this->createUnit('box');
        $to   = $this->createUnit('pcs');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/units/conversions', [
                'from_unit_id' => $from->id,
                'to_unit_id'   => $to->id,
                'factor'       => 12,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.factor', '12.00000000');

        $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/units/conversions');
        $list->assertStatus(200);
        $list->assertJsonFragment(['factor' => '12.00000000']);
    }

    public function test_unit_store_and_delete(): void
    {
        $branch = $this->createBranch($this->company);
        $user   = $this->createUser($this->company, $branch);
        $this->createActiveSubscription($this->company);

        $store = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/units', [
                'name'   => 'Dozen',
                'symbol' => 'dz',
                'type'   => 'quantity',
            ]);

        $store->assertStatus(201);
        $id = $store->json('data.id');

        $delete = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/units/{$id}");

        $delete->assertStatus(200);
        $this->assertDatabaseMissing('units', ['id' => $id]);
    }
}
