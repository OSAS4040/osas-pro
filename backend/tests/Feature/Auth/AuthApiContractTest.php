<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

/**
 * Locks the API contract: /api/* must always speak JSON (no HTML / web redirects).
 */
class AuthApiContractTest extends TestCase
{
    public function test_login_without_accept_header_still_returns_json(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->post('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<!DOCTYPE', $response->getContent());
        $this->assertStringStartsWith('{', trim($response->getContent()));
    }

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $tenant = $this->createTenant('owner');

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user', 'trace_id']);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_me_with_valid_bearer_token_returns_user(): void
    {
        $tenant = $this->createTenant('owner');

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure(['data', 'permissions', 'trace_id']);

        $this->assertSame($tenant['user']->email, $response->json('data.email'));
    }

    public function test_me_without_token_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_logout_with_valid_token_returns_200_json(): void
    {
        $tenant = $this->createTenant('owner');

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);
        $token = $login->json('token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonStructure(['message', 'trace_id']);
    }

    public function test_unknown_api_route_returns_404_json(): void
    {
        $response = $this->getJson('/api/v1/not-existing-route-xyz');

        $response->assertNotFound();
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonStructure(['message', 'trace_id']);
    }

    public function test_me_with_invalid_token_returns_401_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-plain-token')
            ->getJson('/api/v1/auth/me');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
