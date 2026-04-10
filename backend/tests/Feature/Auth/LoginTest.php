<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_user_can_login_with_valid_credentials(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user'        => ['id', 'uuid', 'name', 'email', 'role', 'company_id'],
                'permissions',
                'trace_id',
            ]);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        $tenant = $this->createTenant('owner');
        $email  = $tenant['user']->email;

        $this->postJson('/api/v1/auth/login', [
            'email'    => strtoupper($email),
            'password' => 'Password123!',
        ])->assertStatus(200)
            ->assertJsonPath('user.id', $tenant['user']->id);
    }

    public function test_login_picks_user_matching_password_when_email_exists_on_multiple_companies(): void
    {
        $companyA = $this->createCompany(['name' => 'Company A']);
        $branchA  = $this->createBranch($companyA);
        $this->createActiveSubscription($companyA);

        $companyB = $this->createCompany(['name' => 'Company B']);
        $branchB  = $this->createBranch($companyB);
        $this->createActiveSubscription($companyB);

        $sharedEmail = 'same-email@multi-tenant.test';

        $userA = User::create([
            'uuid'       => \Illuminate\Support\Str::uuid(),
            'company_id' => $companyA->id,
            'branch_id'  => $branchA->id,
            'name'       => 'User A',
            'email'      => $sharedEmail,
            'password'   => 'Password-Alpha-9!',
            'role'       => UserRole::Owner,
            'status'     => UserStatus::Active,
            'is_active'  => true,
        ]);

        $userB = User::create([
            'uuid'       => \Illuminate\Support\Str::uuid(),
            'company_id' => $companyB->id,
            'branch_id'  => $branchB->id,
            'name'       => 'User B',
            'email'      => $sharedEmail,
            'password'   => 'Password-Bravo-9!',
            'role'       => UserRole::Owner,
            'status'     => UserStatus::Active,
            'is_active'  => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $sharedEmail,
            'password' => 'Password-Bravo-9!',
        ])->assertStatus(200)
            ->assertJsonPath('user.id', $userB->id)
            ->assertJsonPath('user.company_id', $companyB->id);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $sharedEmail,
            'password' => 'Password-Alpha-9!',
        ])->assertStatus(200)
            ->assertJsonPath('user.id', $userA->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertUnauthorized()
            ->assertJsonFragment(['message' => 'The provided credentials are incorrect.']);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $tenant = $this->createTenant();
        $tenant['user']->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_returns_otp_challenge_when_saas_otp_enabled(): void
    {
        Config::set('saas.login_otp_enabled', true);
        Mail::fake();

        $tenant = $this->createTenant('owner');

        $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ])->assertStatus(200)
            ->assertJsonPath('otp_required', true)
            ->assertJsonStructure(['challenge_id', 'expires_in', 'message', 'trace_id']);
    }

    public function test_forgot_password_returns_generic_success(): void
    {
        Mail::fake();
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $tenant['user']->email,
        ])->assertStatus(200)
            ->assertJsonStructure(['message', 'trace_id']);
    }

    public function test_public_landing_plans_returns_config(): void
    {
        $this->getJson('/api/v1/public/landing-plans')
            ->assertStatus(200)
            ->assertJsonPath('data.section_title', config('landing.section_title'))
            ->assertJsonStructure(['data' => ['plans'], 'trace_id']);
    }

    public function test_user_can_logout(): void
    {
        $tenant = $this->createTenant();

        $this->actingAsUser($tenant['user'])
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out.']);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $tenant = $this->createTenant();

        $response = $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $tenant['user']->id)
            ->assertJsonPath('data.company_id', $tenant['company']->id)
            ->assertJsonStructure(['data', 'permissions', 'trace_id']);
    }

    public function test_register_creates_company_branch_user_subscription(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company_name'          => 'New Auto Center',
            'name'                  => 'John Owner',
            'email'                 => 'john@newcenter.sa',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone'                 => '+966500000001',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['token', 'user', 'permissions', 'trace_id']);

        $this->assertDatabaseHas('companies', ['name' => 'New Auto Center']);
        $this->assertDatabaseHas('users', ['email' => 'john@newcenter.sa', 'role' => 'owner']);
        $this->assertDatabaseHas('subscriptions', ['plan' => 'trial', 'status' => 'active']);
        $this->assertDatabaseHas('branches', ['code' => 'MAIN', 'is_main' => true]);
    }
}
