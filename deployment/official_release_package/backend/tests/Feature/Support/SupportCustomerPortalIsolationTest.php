<?php

namespace Tests\Feature\Support;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SupportCustomerPortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::factory()->create();
    }

    private function customerUser(Company $company): User
    {
        return User::factory()->create([
            'company_id'  => $company->id,
            'role'        => UserRole::Customer,
            'customer_id' => null,
        ]);
    }

    private function staffUser(Company $company): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role'       => UserRole::Manager,
        ]);
    }

    public function test_customer_cannot_list_other_users_tickets_by_created_by(): void
    {
        $company = $this->makeCompany();
        $staff   = $this->staffUser($company);
        $custA   = $this->customerUser($company);
        $custB   = $this->customerUser($company);

        $tB = SupportTicket::create([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'ticket_number'   => 'T-9001',
            'company_id'      => $company->id,
            'created_by'      => $custB->id,
            'subject'         => 'B only',
            'description'     => 'x',
            'status'          => 'open',
            'priority'        => 'low',
        ]);

        Sanctum::actingAs($custA);
        $res = $this->getJson('/api/v1/support/tickets');
        $res->assertOk();
        $ids = collect($res->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($tB->id, $ids);
    }

    public function test_customer_cannot_view_foreign_ticket(): void
    {
        $company = $this->makeCompany();
        $custA   = $this->customerUser($company);
        $custB   = $this->customerUser($company);

        $tB = SupportTicket::create([
            'uuid'            => (string) \Illuminate\Support\Str::uuid(),
            'ticket_number'   => 'T-9002',
            'company_id'      => $company->id,
            'created_by'      => $custB->id,
            'subject'         => 'B only',
            'description'     => 'x',
            'status'          => 'open',
            'priority'        => 'low',
        ]);

        Sanctum::actingAs($custA);
        $this->getJson("/api/v1/support/tickets/{$tB->id}")->assertStatus(403);
    }

    public function test_stats_for_customer_excludes_top_agents(): void
    {
        $company = $this->makeCompany();
        $user    = $this->customerUser($company);
        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/support/stats');
        $res->assertOk();
        $this->assertSame([], $res->json('data.top_agents') ?? []);
    }
}
