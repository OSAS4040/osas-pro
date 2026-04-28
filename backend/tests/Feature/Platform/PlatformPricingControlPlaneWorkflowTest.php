<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Customer;
use App\Models\PlatformCustomerPriceVersion;
use App\Models\PlatformPricingAuditLog;
use App\Models\PlatformPricingRequest;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformPricingControlPlaneWorkflowTest extends TestCase
{
    public function test_full_happy_path_creates_reference_price_version_and_audit_trail(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2b',
            'name' => 'Pricing Customer',
            'is_active' => true,
        ]);

        $creator = $this->createStandalonePlatformOperator('pc-creator-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_creator',
        ]);
        $reviewer = $this->createStandalonePlatformOperator('pc-review-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_reviewer',
        ]);
        $approver = $this->createStandalonePlatformOperator('pc-approve-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_approver',
        ]);

        $create = $this->actingAsUser($creator)->postJson('/api/v1/platform/pricing/requests', [
            'company_id' => $tenant['company']->id,
            'customer_id' => $customer->id,
            'title' => 'Q1 fleet oil',
            'lines' => [
                ['service_code' => 'oil_change', 'quantity' => 1],
            ],
        ]);
        $create->assertCreated();
        $uuid = (string) $create->json('data.uuid');

        $this->actingAsUser($creator)->postJson("/api/v1/platform/pricing/requests/{$uuid}/submit-for-review")->assertOk();

        $this->actingAsUser($reviewer)->postJson("/api/v1/platform/pricing/requests/{$uuid}/begin-review")->assertOk();
        $this->actingAsUser($reviewer)->postJson("/api/v1/platform/pricing/requests/{$uuid}/complete-review", [
            'recommendation' => [
                'summary' => 'Accept primary quote',
                'recommended_sell_total' => 500,
            ],
        ])->assertOk();
        $this->actingAsUser($reviewer)->postJson("/api/v1/platform/pricing/requests/{$uuid}/escalate")->assertOk();

        $this->actingAsUser($approver)->postJson("/api/v1/platform/pricing/requests/{$uuid}/approve", [
            'sell_snapshot' => [
                ['service_code' => 'oil_change', 'unit_price' => 120, 'currency' => 'SAR'],
            ],
        ])->assertOk();

        $req = PlatformPricingRequest::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame('approved', $req->status->value);
        $this->assertNotNull($req->approved_at);
        $this->assertGreaterThanOrEqual(5, PlatformPricingAuditLog::query()->where('platform_pricing_request_id', $req->id)->count());

        $ref = PlatformCustomerPriceVersion::query()
            ->where('company_id', $tenant['company']->id)
            ->where('customer_id', $customer->id)
            ->where('is_reference', true)
            ->firstOrFail();
        $this->assertSame(1, (int) $ref->version_no);
    }

    public function test_cannot_approve_without_review_completion(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2b',
            'name' => 'C2',
            'is_active' => true,
        ]);
        $approver = $this->createStandalonePlatformOperator('pc-ap2-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_approver',
        ]);
        $creator = $this->createStandalonePlatformOperator('pc-cr2-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_creator',
        ]);

        $uuid = (string) $this->actingAsUser($creator)->postJson('/api/v1/platform/pricing/requests', [
            'company_id' => $tenant['company']->id,
            'customer_id' => $customer->id,
            'lines' => [['service_code' => 'tire_rotation']],
        ])->assertCreated()->json('data.uuid');

        $this->actingAsUser($creator)->postJson("/api/v1/platform/pricing/requests/{$uuid}/submit-for-review")->assertOk();

        $this->actingAsUser($approver)->postJson("/api/v1/platform/pricing/requests/{$uuid}/approve", [
            'sell_snapshot' => [['service_code' => 'tire_rotation', 'unit_price' => 50]],
        ])->assertStatus(422);
    }

    public function test_creator_cannot_call_approve_endpoint(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2b',
            'name' => 'C3',
            'is_active' => true,
        ]);
        $creator = $this->createStandalonePlatformOperator('pc-cr3-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_creator',
        ]);
        $uuid = (string) $this->actingAsUser($creator)->postJson('/api/v1/platform/pricing/requests', [
            'company_id' => $tenant['company']->id,
            'customer_id' => $customer->id,
            'lines' => [['service_code' => 'x']],
        ])->assertCreated()->json('data.uuid');

        $this->actingAsUser($creator)->postJson("/api/v1/platform/pricing/requests/{$uuid}/approve", [
            'sell_snapshot' => [['service_code' => 'x', 'unit_price' => 1]],
        ])->assertForbidden();
    }

    public function test_cannot_mutate_reference_price_version_snapshot(): void
    {
        $tenant = $this->createTenant('owner');
        $customer = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'branch_id' => $tenant['branch']->id,
            'type' => 'b2b',
            'name' => 'C4',
            'is_active' => true,
        ]);
        $v = PlatformCustomerPriceVersion::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $tenant['company']->id,
            'customer_id' => $customer->id,
            'contract_id' => null,
            'root_contract_id' => null,
            'platform_pricing_request_id' => null,
            'version_no' => 1,
            'is_reference' => true,
            'sell_snapshot' => ['a' => 1],
            'activated_at' => now(),
        ]);

        $this->expectException(\DomainException::class);
        $v->update(['sell_snapshot' => ['a' => 2]]);
    }
}
