<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\WalletTopUpRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WalletTopUpRequestWorkflowTest extends TestCase
{
    private array $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->tenant = $this->createTenant('owner');
        $this->customer = Customer::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->tenant['company']->id,
            'branch_id' => $this->tenant['branch']->id,
            'type' => 'individual',
            'name' => 'Wallet TU Customer',
            'is_active' => true,
        ]);
    }

    private function customerMainBalance(): string
    {
        $w = CustomerWallet::where('company_id', $this->tenant['company']->id)
            ->where('customer_id', $this->customer->id)
            ->where('wallet_type', WalletType::CustomerMain->value)
            ->first();

        return $w ? (string) $w->balance : '0.0000';
    }

    public function test_create_request_does_not_credit_wallet(): void
    {
        $response = $this->actingAsUser($this->tenant['user'])
            ->postJson('/api/v1/wallet-top-up-requests', [
                'customer_id' => $this->customer->id,
                'target' => 'individual',
                'amount' => 250.5,
                'payment_method' => 'cash',
                'notes_from_customer' => 'طلب شحن — اختبار',
            ]);

        $response->assertStatus(201);
        $this->assertEquals('0.0000', $this->customerMainBalance());
    }

    public function test_approve_credits_wallet_once_and_idempotent_second_approve(): void
    {
        $this->actingAsUser($this->tenant['user'])
            ->postJson('/api/v1/wallet-top-up-requests', [
                'customer_id' => $this->customer->id,
                'target' => 'individual',
                'amount' => 100,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $req = WalletTopUpRequest::firstOrFail();

        $this->actingAsUser($this->tenant['user'])
            ->postJson("/api/v1/admin/wallet-top-up-requests/{$req->id}/approve", ['note' => 'OK'])
            ->assertOk();

        $this->assertEquals('100.0000', $this->customerMainBalance());
        $req->refresh();
        $this->assertSame('approved', $req->status->value);
        $this->assertNotNull($req->approved_wallet_transaction_id);

        $this->actingAsUser($this->tenant['user'])
            ->postJson("/api/v1/admin/wallet-top-up-requests/{$req->id}/approve", ['note' => 'retry'])
            ->assertOk();

        $this->assertEquals('100.0000', $this->customerMainBalance());
    }

    public function test_reject_does_not_change_balance(): void
    {
        $this->actingAsUser($this->tenant['user'])
            ->postJson('/api/v1/wallet-top-up-requests', [
                'customer_id' => $this->customer->id,
                'target' => 'individual',
                'amount' => 50,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $req = WalletTopUpRequest::firstOrFail();

        $this->actingAsUser($this->tenant['user'])
            ->postJson("/api/v1/admin/wallet-top-up-requests/{$req->id}/reject", [
                'review_notes' => 'بيانات ناقصة',
            ])
            ->assertOk();

        $this->assertEquals('0.0000', $this->customerMainBalance());
        $this->assertSame('rejected', $req->fresh()->status->value);
    }

    public function test_return_resubmit_then_approve(): void
    {
        $this->actingAsUser($this->tenant['user'])
            ->postJson('/api/v1/wallet-top-up-requests', [
                'customer_id' => $this->customer->id,
                'target' => 'individual',
                'amount' => 40,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $req = WalletTopUpRequest::firstOrFail();

        $this->actingAsUser($this->tenant['user'])
            ->postJson("/api/v1/admin/wallet-top-up-requests/{$req->id}/return", [
                'review_notes' => 'أرفق إيصالاً',
            ])
            ->assertOk();

        $this->assertSame('returned_for_revision', $req->fresh()->status->value);

        $staff = $this->createUser($this->tenant['company'], $this->tenant['branch'], 'staff');

        $this->actingAsUser($staff)
            ->postJson("/api/v1/wallet-top-up-requests/{$req->id}/resubmit")
            ->assertStatus(403);

        $this->actingAsUser($this->tenant['user'])
            ->postJson("/api/v1/wallet-top-up-requests/{$req->id}/resubmit")
            ->assertOk();

        $this->assertSame('pending', $req->fresh()->status->value);

        $this->actingAsUser($this->tenant['user'])
            ->postJson("/api/v1/admin/wallet-top-up-requests/{$req->id}/approve")
            ->assertOk();

        $this->assertEquals('40.0000', $this->customerMainBalance());
    }
}
