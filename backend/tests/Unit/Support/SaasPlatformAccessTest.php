<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\SaasPlatformAccess;
use Tests\TestCase;

class SaasPlatformAccessTest extends TestCase
{
    public function test_platform_operator_when_email_in_allowlist(): void
    {
        config([
            'saas.platform_admin_emails' => ['ops@platform.example'],
            'saas.platform_admin_phones' => [],
        ]);

        $user = new User(['email' => 'Ops@Platform.example', 'company_id' => null]);

        $this->assertTrue(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_false_when_email_allowlisted_but_user_has_company(): void
    {
        config([
            'saas.platform_admin_emails' => ['ops@platform.example'],
            'saas.platform_admin_phones' => [],
        ]);

        $user = new User(['email' => 'ops@platform.example', 'company_id' => 1]);

        $this->assertFalse(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_false_when_list_empty(): void
    {
        config([
            'saas.platform_admin_emails' => [],
            'saas.platform_admin_phones' => [],
        ]);

        $user = new User(['email' => 'ops@platform.example']);

        $this->assertFalse(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_when_phone_allowlisted_and_no_company(): void
    {
        config([
            'saas.platform_admin_emails' => [],
            'saas.platform_admin_phones' => ['0504644804'],
        ]);

        $user = new User([
            'company_id' => null,
            'phone'      => '966504644804',
        ]);

        $this->assertTrue(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_false_when_phone_allowlisted_but_user_has_company(): void
    {
        config([
            'saas.platform_admin_emails' => [],
            'saas.platform_admin_phones' => ['0504644804'],
        ]);

        $user = new User([
            'company_id' => 99,
            'phone'      => '966504644804',
        ]);

        $this->assertFalse(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_when_is_platform_user_without_allowlist(): void
    {
        config([
            'saas.platform_admin_emails' => [],
            'saas.platform_admin_phones' => [],
        ]);

        $user = new User([
            'company_id'       => null,
            'is_platform_user' => true,
        ]);

        $this->assertTrue(SaasPlatformAccess::isPlatformOperator($user));
    }

    public function test_platform_operator_when_is_platform_user_with_company_anchor(): void
    {
        config([
            'saas.platform_admin_emails' => [],
            'saas.platform_admin_phones' => [],
        ]);

        $user = new User([
            'company_id'       => 42,
            'is_platform_user' => true,
        ]);

        $this->assertTrue(SaasPlatformAccess::isPlatformOperator($user));
    }
}
