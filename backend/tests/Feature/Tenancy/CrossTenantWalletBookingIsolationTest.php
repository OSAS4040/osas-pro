<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Enums\WalletType;
use App\Models\Bay;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerWallet;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * عزل المحفظة والحجوزات بين الشركات (مسارات ورشة بصلاحية invoices.view).
 */
final class CrossTenantWalletBookingIsolationTest extends TestCase
{
    public function test_wallet_overview_never_includes_other_company_wallet_ids(): void
    {
        $t1 = $this->createTenant('owner');
        $t2 = $this->createTenant('owner');

        $c1 = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'type' => 'b2c',
            'name' => 'Wallet Iso 1',
            'is_active' => true,
        ]);
        $c2 = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $t2['company']->id,
            'branch_id' => $t2['branch']->id,
            'type' => 'b2c',
            'name' => 'Wallet Iso 2',
            'is_active' => true,
        ]);

        $w1 = CustomerWallet::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'customer_id' => $c1->id,
            'vehicle_id' => null,
            'wallet_type' => WalletType::CustomerMain->value,
            'status' => 'active',
            'balance' => 1111,
            'currency' => 'SAR',
            'version' => 1,
        ]);
        $w2 = CustomerWallet::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $t2['company']->id,
            'branch_id' => $t2['branch']->id,
            'customer_id' => $c2->id,
            'vehicle_id' => null,
            'wallet_type' => WalletType::CustomerMain->value,
            'status' => 'active',
            'balance' => 2222,
            'currency' => 'SAR',
            'version' => 1,
        ]);

        $res = $this->actingAsUser($t2['user'])->getJson('/api/v1/wallet');
        $res->assertOk();
        $ids = collect($res->json('wallets.data') ?? [])->pluck('id')->all();
        $this->assertContains($w2->id, $ids);
        $this->assertNotContains($w1->id, $ids);
    }

    public function test_wallet_transactions_reject_foreign_wallet_id(): void
    {
        $t1 = $this->createTenant('owner');
        $t2 = $this->createTenant('owner');

        $c1 = Customer::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'type' => 'b2c',
            'name' => 'W TX 1',
            'is_active' => true,
        ]);

        $w1 = CustomerWallet::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'customer_id' => $c1->id,
            'vehicle_id' => null,
            'wallet_type' => WalletType::CustomerMain->value,
            'status' => 'active',
            'balance' => 50,
            'currency' => 'SAR',
            'version' => 1,
        ]);

        $this->actingAsUser($t2['user'])
            ->getJson('/api/v1/wallet/transactions?wallet_id='.$w1->id)
            ->assertNotFound();
    }

    public function test_bookings_index_never_lists_other_company_bookings(): void
    {
        $t1 = $this->createTenant('owner');
        $t2 = $this->createTenant('owner');

        $bay = Bay::create([
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'code' => 'BAY-XISO-'.Str::upper(Str::random(3)),
            'name' => 'Bay Cross Iso',
            'status' => 'available',
        ]);

        $booking = Booking::create([
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'bay_id' => $bay->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ]);

        $res = $this->actingAsUser($t2['user'])->getJson('/api/v1/bookings?per_page=100');
        $res->assertOk();
        $ids = collect($res->json('data') ?? [])->pluck('id')->all();
        $this->assertNotContains($booking->id, $ids);
    }

    public function test_patch_booking_foreign_company_returns_404(): void
    {
        $t1 = $this->createTenant('owner');
        $t2 = $this->createTenant('owner');

        $bay = Bay::create([
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'code' => 'BAY-PATCH-'.Str::upper(Str::random(3)),
            'name' => 'Bay Patch',
            'status' => 'in_use',
        ]);

        $booking = Booking::create([
            'company_id' => $t1['company']->id,
            'branch_id' => $t1['branch']->id,
            'bay_id' => $bay->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'duration_minutes' => 60,
            'status' => 'confirmed',
        ]);

        $this->actingAsUser($t2['user'])
            ->patchJson('/api/v1/bookings/'.$booking->id, ['action' => 'start'])
            ->assertNotFound();
    }
}
