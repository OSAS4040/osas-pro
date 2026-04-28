<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\Customer;
use App\Models\WalletTopUpRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * @see docs/phases/PHASE_00_CLOSURE_REPORT.md — تعليمات تحويل شحن المحفظة
 */
#[Group('phase0')]
final class WalletTopUpTransferInstructionsTest extends TestCase
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
            'name' => 'Transfer PDF Customer',
            'is_active' => true,
        ]);
    }

    private function createCashTopUpRequest(): WalletTopUpRequest
    {
        $this->actingAsUser($this->tenant['user'])
            ->postJson('/api/v1/wallet-top-up-requests', [
                'customer_id' => $this->customer->id,
                'target' => 'individual',
                'amount' => 75,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        return WalletTopUpRequest::firstOrFail();
    }

    public function test_transfer_instructions_returns_422_when_no_bank_configuration(): void
    {
        $company = $this->tenant['company']->fresh();
        $settings = is_array($company->settings) ? $company->settings : [];
        unset($settings['wallet_treasury_accounts']);
        $company->update([
            'settings' => $settings,
            'bank_name' => null,
            'iban' => null,
        ]);

        $req = $this->createCashTopUpRequest();

        $this->actingAsUser($this->tenant['user'])
            ->getJson("/api/v1/wallet-top-up-requests/{$req->id}/transfer-instructions")
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'trace_id']);
    }

    public function test_transfer_instructions_streams_pdf_when_treasury_accounts_configured(): void
    {
        $company = $this->tenant['company']->fresh();
        $settings = is_array($company->settings) ? $company->settings : [];
        $settings['wallet_treasury_accounts'] = [
            [
                'bank_name' => 'اختبار البنك',
                'iban' => 'SA0380000000608010167519',
                'account_number' => '',
                'beneficiary_label' => 'شركة الاختبار',
            ],
        ];
        $company->update(['settings' => $settings]);

        $req = $this->createCashTopUpRequest();

        $response = $this->actingAsUser($this->tenant['user'])
            ->get("/api/v1/wallet-top-up-requests/{$req->id}/transfer-instructions");

        $response->assertOk();
        $ct = strtolower((string) $response->headers->get('content-type', ''));
        $this->assertStringContainsString('pdf', $ct);
    }

    public function test_transfer_instructions_forbidden_for_staff_who_is_not_requester(): void
    {
        $req = $this->createCashTopUpRequest();
        $staff = $this->createUser($this->tenant['company'], $this->tenant['branch'], 'staff');

        $this->actingAsUser($staff)
            ->getJson("/api/v1/wallet-top-up-requests/{$req->id}/transfer-instructions")
            ->assertForbidden();
    }
}
