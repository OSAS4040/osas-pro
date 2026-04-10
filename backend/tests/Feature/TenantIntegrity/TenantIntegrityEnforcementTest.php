<?php

namespace Tests\Feature\TenantIntegrity;

use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIntegrityEnforcementTest extends TestCase
{
    public function test_login_fails_when_company_is_soft_deleted(): void
    {
        $tenant = $this->createTenant('owner');
        $tenant['company']->delete();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $tenant['user']->email,
            'password' => 'Password123!',
        ]);

        $response->assertForbidden();
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_cannot_save_user_with_branch_from_other_company(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant integrity: users.branch_id must reference');

        User::withoutGlobalScope('tenant')->create([
            'uuid'       => (string) Str::uuid(),
            'company_id' => $a['company']->id,
            'branch_id'  => $b['branch']->id,
            'name'       => 'Bad Link',
            'email'      => 'bad-branch-link-' . uniqid('', true) . '@test.sa',
            'password'   => 'Password123!',
            'role'       => 'cashier',
            'status'     => 'active',
            'is_active'  => true,
        ]);
    }

    public function test_integrity_command_reports_invalid_branch(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        \Illuminate\Support\Facades\DB::table('users')->where('id', $a['user']->id)->update([
            'branch_id' => $b['branch']->id,
        ]);

        $this->artisan('tenant:integrity')
            ->assertFailed();
    }

    public function test_integrity_command_fix_branches_repairs_row(): void
    {
        $a = $this->createTenant('owner');
        $b = $this->createTenant('owner');

        \Illuminate\Support\Facades\DB::table('users')->where('id', $a['user']->id)->update([
            'branch_id' => $b['branch']->id,
        ]);

        $this->artisan('tenant:integrity', ['--fix-branches' => true])
            ->assertSuccessful();

        $a['user']->refresh();
        $this->assertSame($a['branch']->id, (int) $a['user']->branch_id);
    }
}
