<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mirror TraceRequestMiddleware when tests call services outside HTTP (e.g. WorkOrderService).
        if (! app()->bound('trace_id')) {
            app()->instance('trace_id', (string) Str::uuid());
        }
        if (! app()->bound('correlation_id')) {
            app()->instance('correlation_id', (string) Str::uuid());
        }
    }

    protected function createCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'uuid'      => Str::uuid(),
            'name'      => 'Test Company',
            'currency'  => 'SAR',
            'timezone'  => 'Asia/Riyadh',
            'status'    => 'active',
            'is_active' => true,
        ], $overrides));
    }

    protected function createBranch(Company $company, array $overrides = []): Branch
    {
        return Branch::create(array_merge([
            'uuid'      => Str::uuid(),
            'company_id'=> $company->id,
            'name'      => 'Main Branch',
            'code'      => 'MAIN',
            'status'    => 'active',
            'is_main'   => true,
            'is_active' => true,
        ], $overrides));
    }

    protected function createUser(Company $company, Branch $branch, string $role = 'owner', array $overrides = []): User
    {
        return User::create(array_merge([
            'uuid'       => Str::uuid(),
            'company_id' => $company->id,
            'branch_id'  => $branch->id,
            'name'       => ucfirst($role) . ' User',
            'email'      => $role . '_' . Str::random(6) . '@test.sa',
            'password'   => bcrypt('Password123!'),
            'role'       => $role,
            'status'     => 'active',
            'is_active'  => true,
        ], $overrides));
    }

    protected function createActiveSubscription(Company $company, string $plan = 'professional'): Subscription
    {
        return Subscription::create([
            'uuid'        => Str::uuid(),
            'company_id'  => $company->id,
            'plan'        => $plan,
            'status'      => 'active',
            'starts_at'   => now()->subDay(),
            'ends_at'     => now()->addYear(),
            'max_branches'=> 5,
            'max_users'   => 20,
        ]);
    }

    protected function createTenant(string $role = 'owner'): array
    {
        $company      = $this->createCompany();
        $branch       = $this->createBranch($company);
        $user         = $this->createUser($company, $branch, $role);
        $subscription = $this->createActiveSubscription($company);

        return compact('company', 'branch', 'user', 'subscription');
    }

    protected function actingAsUser(User $user): static
    {
        return $this->actingAs($user, 'sanctum');
    }

    protected function makeAuthUser(Company $company, string $role = 'owner'): User
    {
        $branch = Branch::where('company_id', $company->id)->first()
            ?? $this->createBranch($company);

        return $this->createUser($company, $branch, $role);
    }
}
