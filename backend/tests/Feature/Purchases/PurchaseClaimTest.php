<?php

namespace Tests\Feature\Purchases;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseClaim;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_and_list_own_claim(): void
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
            'reference_number' => 'PO-T-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'currency' => 'SAR',
            'notes' => null,
        ]);

        $this->actingAs($user, 'sanctum');

        $res = $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
            'description' => 'Need parts reimbursement',
            'requested_amount' => 120.5,
        ])->assertCreated();

        $this->assertStringContainsString('Need parts reimbursement', (string) $res->json('data.description'));
        $this->assertSame($purchase->id, $res->json('data.purchases.0.id'));

        $this->getJson('/api/v1/purchase-claims')->assertOk()
            ->assertJsonPath('data.data.0.purchases.0.reference_number', $purchase->reference_number);
    }

    public function test_cannot_submit_same_purchase_while_pending_claim_exists(): void
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
            'reference_number' => 'PO-DUP-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 50,
            'paid_amount' => 0,
            'currency' => 'SAR',
            'notes' => null,
        ]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
        ])->assertCreated();

        $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_ALREADY_LINKED_TO_CLAIM');
    }

    public function test_cannot_submit_purchase_linked_to_approved_claim(): void
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
            'reference_number' => 'PO-APP-'.Str::upper(Str::random(5)),
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

        $this->actingAs($staff, 'sanctum');
        $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
        ])->assertCreated();

        $mgr = $this->createUser($company, $branch, 'manager');
        $this->actingAs($mgr, 'sanctum');
        $claimId = PurchaseClaim::query()->where('company_id', $company->id)->latest('id')->value('id');
        $this->assertNotNull($claimId);
        $this->patchJson("/api/v1/purchase-claims/{$claimId}/review", [
            'status' => 'approved',
            'admin_notes' => 'ok',
        ])->assertOk()
            ->assertJsonPath('data.platform_review_status', 'pending');

        $this->actingAs($staff, 'sanctum');
        $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PURCHASE_ALREADY_LINKED_TO_CLAIM');
    }

    public function test_manager_can_review_claim(): void
    {
        $t = $this->createTenant('staff');
        $staff = $t['user'];
        $company = $t['company'];
        $branch = $t['branch'];

        $claim = PurchaseClaim::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'created_by_user_id' => $staff->id,
            'status' => 'pending',
            'description' => 'Test',
        ]);

        $mgr = $this->createUser($company, $branch, 'manager');
        $this->actingAs($mgr, 'sanctum');

        $this->patchJson("/api/v1/purchase-claims/{$claim->id}/review", [
            'status' => 'approved',
            'admin_notes' => 'OK',
        ])->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.platform_review_status', 'pending');
    }

    public function test_after_platform_reject_staff_can_open_new_claim_on_same_purchase(): void
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
            'reference_number' => 'PO-REL-'.Str::upper(Str::random(5)),
            'status' => PurchaseStatus::Received,
            'billing_flow_type' => 'platform_to_provider_purchase',
            'subtotal' => 40,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 40,
            'paid_amount' => 0,
            'currency' => 'SAR',
            'notes' => null,
        ]);

        $this->actingAs($staff, 'sanctum');
        $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
        ])->assertCreated();

        $mgr = $this->createUser($company, $branch, 'manager');
        $claimId = PurchaseClaim::query()->where('company_id', $company->id)->latest('id')->value('id');
        $this->actingAs($mgr, 'sanctum');
        $this->patchJson("/api/v1/purchase-claims/{$claimId}/review", [
            'status' => 'approved',
            'admin_notes' => 'tenant ok',
        ])->assertOk();

        $platform = $this->createStandalonePlatformOperator('plat-rej-'.Str::random(6).'@platform.test', [
            'platform_role' => 'finance_admin',
        ]);
        $this->actingAsUser($platform);
        $this->patchJson("/api/v1/platform/purchase-claims/{$claimId}/review", [
            'status' => 'rejected',
            'platform_review_notes' => 'missing docs',
        ])->assertOk()
            ->assertJsonPath('data.platform_review_status', 'rejected');

        $this->actingAs($staff, 'sanctum');
        $this->postJson('/api/v1/purchase-claims', [
            'purchase_ids' => [$purchase->id],
            'description' => 'retry after platform rejection',
        ])->assertCreated();
    }
}
