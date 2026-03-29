<?php

namespace Tests\Feature\Auth;

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

    public function test_login_fails_with_wrong_password(): void
    {
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
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
