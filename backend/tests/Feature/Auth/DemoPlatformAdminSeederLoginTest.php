<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\LoginPrincipalKind;
use App\Models\User;
use Database\Seeders\DefaultAdminSeeder;
use Database\Seeders\DemoPlatformAdminSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * يثبت أن بيانات DemoPlatformAdminSeeder تطابق مسار تسجيل الدخول الفعلي (بدون تخمين).
 *
 * @see docs/phases/PHASE_00_CLOSURE_REPORT.md
 */
#[Group('phase0')]
final class DemoPlatformAdminSeederLoginTest extends TestCase
{
    public function test_after_seeder_password_login_returns_platform_employee(): void
    {
        Config::set('app.debug', true);
        Config::set('saas.login_otp_enabled', false);

        (new DefaultAdminSeeder)->run();
        (new DemoPlatformAdminSeeder)->run();

        $login = $this->postJson('/api/v1/auth/login', [
            'email'    => DemoPlatformAdminSeeder::DEMO_EMAIL,
            'password' => DemoPlatformAdminSeeder::DEMO_PASSWORD,
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('account_context.principal_kind', LoginPrincipalKind::PlatformEmployee->value)
            ->assertJsonPath('account_context.guard_hint', 'staff')
            ->assertJsonPath('account_context.home_route_hint', '/work-orders')
            ->assertJsonStructure(['token', 'user', 'account_context']);

        $this->assertGreaterThan(0, (int) data_get($login->json(), 'user.company_id'));
    }

    public function test_find_user_matching_password_logic_matches_hash_in_database(): void
    {
        (new DemoPlatformAdminSeeder)->run();

        $row = User::withoutGlobalScope('tenant')
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(DemoPlatformAdminSeeder::DEMO_EMAIL)])
            ->first();

        $this->assertNotNull($row);
        $hash = $row->getRawOriginal('password');
        $this->assertIsString($hash);
        $this->assertTrue(Hash::check(DemoPlatformAdminSeeder::DEMO_PASSWORD, $hash));
    }

    public function test_wrong_password_returns_401_with_platform_demo_hint_outside_dev_hint(): void
    {
        Config::set('app.debug', false);
        Config::set('app.env', 'local');

        (new DemoPlatformAdminSeeder)->run();

        $res = $this->postJson('/api/v1/auth/login', [
            'email'    => DemoPlatformAdminSeeder::DEMO_EMAIL,
            'password' => 'definitely-not-the-demo-password-xyz',
        ]);

        $res->assertStatus(401);
        $this->assertArrayHasKey('platform_demo_hint', $res->json());
        $this->assertNotEmpty($res->json('platform_demo_hint'));
    }
}
