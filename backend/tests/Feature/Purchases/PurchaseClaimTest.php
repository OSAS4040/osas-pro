<?php

namespace Tests\Feature\Purchases;

use App\Models\PurchaseClaim;
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
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/v1/purchase-claims', [
            'description' => 'Need parts reimbursement',
            'requested_amount' => 120.5,
        ])->assertCreated()
            ->assertJsonPath('data.description', 'Need parts reimbursement');

        $this->getJson('/api/v1/purchase-claims')->assertOk()
            ->assertJsonPath('data.data.0.description', 'Need parts reimbursement');
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
            ->assertJsonPath('data.status', 'approved');
    }
}
