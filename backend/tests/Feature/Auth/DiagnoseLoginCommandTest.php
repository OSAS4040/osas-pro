<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class DiagnoseLoginCommandTest extends TestCase
{
    public function test_diagnose_unknown_email_succeeds_with_warning(): void
    {
        $this->artisan('auth:diagnose', ['email' => 'missing-'.uniqid('', true).'@test.local'])
            ->assertSuccessful()
            ->expectsOutputToContain('No user rows');
    }

    public function test_diagnose_lists_tenant_user_without_printing_secrets(): void
    {
        $tenant = $this->createTenant('owner');
        $email  = $tenant['user']->email;

        $this->artisan('auth:diagnose', ['email' => $email])
            ->assertSuccessful()
            ->expectsOutputToContain('Candidates')
            ->doesntExpectOutputToContain('Password123!');
    }
}
