<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseClaim;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PlatformPurchaseClaimsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_platform_operator_can_list_cross_tenant_claims(): void
    {
        $t = $this->createTenant('staff');
        $user = $t['user'];
        $company = $t['company'];
        $branch = $t['branch'];

        $supplier = Supplier::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'name' => 'مزود منصة',
            'code' => 'PLAT-'.Str::upper(Str::random(4)),
            'is_active' => true,
            'status' => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $user->id,
            'reference_number' => 'PO-PX-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 80,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 80,
            'paid_amount' => 0,
            'currency' => 'SAR',
            'notes' => null,
        ]);

        $claim = PurchaseClaim::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $user->id,
            'status' => 'pending',
            'title' => 'Test claim',
            'description' => 'Platform oversight test',
            'requested_amount' => 80,
        ]);
        $claim->purchases()->sync([$purchase->id]);

        $platformUser = $this->createStandalonePlatformOperator('fin-pc-'.Str::random(6).'@platform.test', [
            'platform_role' => 'finance_admin',
        ]);

        $this->actingAsUser($platformUser)->getJson('/api/v1/platform/purchase-claims')
            ->assertOk()
            ->assertJsonPath('data.data.0.uuid', $claim->uuid)
            ->assertJsonPath('data.data.0.company.name', $company->name);
    }

    public function test_pricing_creator_without_claim_permission_is_forbidden(): void
    {
        $creator = $this->createStandalonePlatformOperator('pc-no-pc-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_creator',
        ]);

        $this->actingAsUser($creator)->getJson('/api/v1/platform/purchase-claims')
            ->assertForbidden();
    }

    public function test_finance_admin_can_approve_platform_review(): void
    {
        $t = $this->createTenant('staff');
        $staff = $t['user'];
        $company = $t['company'];
        $branch = $t['branch'];

        $supplier = Supplier::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'name' => 'مزود منصة',
            'code' => 'PLAT-'.Str::upper(Str::random(4)),
            'is_active' => true,
            'status' => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $staff->id,
            'reference_number' => 'PO-PFV-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 55,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 55,
            'paid_amount' => 0,
            'currency' => 'SAR',
            'notes' => null,
        ]);

        $claim = PurchaseClaim::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'status' => 'approved',
            'platform_review_status' => 'pending',
            'title' => 'Await platform',
            'description' => 'tenant already approved',
            'requested_amount' => 55,
        ]);
        $claim->purchases()->sync([$purchase->id]);

        $platformUser = $this->createStandalonePlatformOperator('fin-pc-apv-'.Str::random(6).'@platform.test', [
            'platform_role' => 'finance_admin',
        ]);

        $this->actingAsUser($platformUser)->patchJson('/api/v1/platform/purchase-claims/'.$claim->id.'/review', [
            'status' => 'approved',
            'platform_review_notes' => 'ok',
        ])->assertOk()
            ->assertJsonPath('data.platform_review_status', 'approved');
    }

    public function test_platform_review_forbidden_without_review_permission(): void
    {
        $t = $this->createTenant('staff');
        $staff = $t['user'];
        $company = $t['company'];
        $branch = $t['branch'];

        $supplier = Supplier::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'name' => 'مزود منصة',
            'code' => 'PLAT-'.Str::upper(Str::random(4)),
            'is_active' => true,
            'status' => 'active',
        ]);

        $purchase = Purchase::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'created_by_user_id' => $staff->id,
            'reference_number' => 'PO-NORV-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 10,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 10,
            'paid_amount' => 0,
            'currency' => 'SAR',
            'notes' => null,
        ]);

        $claim = PurchaseClaim::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'status' => 'approved',
            'platform_review_status' => 'pending',
            'description' => 'needs platform',
            'requested_amount' => 10,
        ]);
        $claim->purchases()->sync([$purchase->id]);

        $creator = $this->createStandalonePlatformOperator('pc-no-review-'.Str::random(6).'@platform.test', [
            'platform_role' => 'platform_pricing_creator',
        ]);

        $this->actingAsUser($creator)->patchJson('/api/v1/platform/purchase-claims/'.$claim->id.'/review', [
            'status' => 'approved',
        ])->assertForbidden();
    }
}
