<?php

namespace Tests;

use App\Enums\UserRole;
use App\Enums\UserStatus;
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
        if (! app()->bound('request_id')) {
            app()->instance('request_id', (string) Str::uuid());
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
            // Default test tenants as platform-approved prepaid (billing gates).
            'financial_model' => 'prepaid',
            'financial_model_status' => 'approved_prepaid',
        ], $overrides));
    }

    /**
     * @param  list<int>  $workOrderIds
     * @param  list<array<string, mixed>>|null  $lines
     */
    protected function obtainSensitivePreviewToken(
        \Illuminate\Contracts\Auth\Authenticatable $user,
        string $operation,
        array $workOrderIds = [],
        ?array $lines = null,
    ): string {
        $payload = ['operation' => $operation];
        if ($workOrderIds !== []) {
            $payload['work_order_ids'] = $workOrderIds;
        }
        if ($lines !== null) {
            $payload['lines'] = $lines;
        }

        $res = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sensitive-operations/preview', $payload);

        $res->assertOk();
        $token = $res->json('data.sensitive_preview_token');
        if ($token === null || $token === '') {
            $this->fail('sensitive preview did not return sensitive_preview_token');
        }

        return (string) $token;
    }

    /**
     * سطر بند واحد صالح لإنشاء أمر عمل (الخدمات تفرض بنداً واحداً على الأقل).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function minimalWorkOrderLineItem(array $overrides = []): array
    {
        return array_merge([
            'item_type' => 'service',
            'name' => 'Test line',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 15,
            'product_id' => null,
        ], $overrides);
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

    /**
     * مستخدم منصة مستقل (company_id = null) — يُطابق شرط SaasPlatformAccess عند ضبط البريد/الجوال في config.
     */
    protected function createStandalonePlatformOperator(string $email, array $overrides = []): User
    {
        return User::withoutGlobalScopes()->create(array_merge([
            'uuid'               => (string) Str::uuid(),
            'company_id'         => null,
            'branch_id'          => null,
            'org_unit_id'        => null,
            'customer_id'        => null,
            'name'               => 'Platform Operator',
            'email'              => $email,
            'password'           => 'Password123!',
            'phone'              => null,
            'role'               => UserRole::Owner,
            'status'             => UserStatus::Active,
            'is_active'          => true,
            'is_platform_user'   => true,
            'platform_role'      => 'super_admin',
            'account_type'       => null,
            'registration_stage' => 'phone_verified',
        ], $overrides));
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
