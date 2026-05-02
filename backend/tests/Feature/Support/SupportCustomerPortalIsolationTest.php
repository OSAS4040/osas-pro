<?php

namespace Tests\Feature\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class SupportCustomerPortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = $this->createCompany();
        $this->createActiveSubscription($company);

        return $company;
    }

    private function customerUser(Company $company): User
    {
        $branch = Branch::where('company_id', $company->id)->first()
            ?? $this->createBranch($company);

        return $this->createUser($company, $branch, 'customer', [
            'customer_id' => null,
        ]);
    }

    private function staffUser(Company $company): User
    {
        $branch = Branch::where('company_id', $company->id)->first()
            ?? $this->createBranch($company);

        return $this->createUser($company, $branch, 'manager');
    }

    public function test_customer_cannot_list_other_users_tickets_by_created_by(): void
    {
        $company = $this->makeCompany();
        $staff = $this->staffUser($company);
        $custA = $this->customerUser($company);
        $custB = $this->customerUser($company);

        $tB = SupportTicket::create([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'T-9001',
            'company_id' => $company->id,
            'created_by' => $custB->id,
            'subject' => 'B only',
            'description' => 'x',
            'status' => 'open',
            'priority' => 'low',
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
        $custA = $this->customerUser($company);
        $custB = $this->customerUser($company);

        $tB = SupportTicket::create([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'T-9002',
            'company_id' => $company->id,
            'created_by' => $custB->id,
            'subject' => 'B only',
            'description' => 'x',
            'status' => 'open',
            'priority' => 'low',
        ]);

        Sanctum::actingAs($custA);
        $this->getJson("/api/v1/support/tickets/{$tB->id}")->assertStatus(403);
    }

    public function test_stats_for_customer_excludes_top_agents(): void
    {
        $company = $this->makeCompany();
        $user = $this->customerUser($company);
        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/support/stats');
        $res->assertOk();
        $this->assertSame([], $res->json('data.top_agents') ?? []);
    }

    public function test_customer_cannot_resolve_ticket_from_other_company(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $custA = $this->customerUser($companyA);
        $staffB = $this->staffUser($companyB);

        $ticketB = SupportTicket::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'ticket_number' => 'T-XT-CO-'.Str::upper(Str::random(4)),
            'company_id' => $companyB->id,
            'created_by' => $staffB->id,
            'subject' => 'Other tenant ticket',
            'description' => 'secret',
            'status' => 'open',
            'priority' => 'low',
        ]);

        Sanctum::actingAs($custA);
        $this->getJson('/api/v1/support/tickets/'.$ticketB->id)->assertNotFound();
    }
}
