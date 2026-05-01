<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TenantBusinessFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class TenantBusinessFeaturesOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_execution_partner_forced_by_company_id_env_mapping(): void
    {
        $company = $this->createCompany(['email' => 'regular@demo.sa']);

        Config::set('tenant_features.platform_execution_partner_company_ids', [$company->id]);
        Config::set('tenant_features.platform_execution_partner_company_emails', []);

        $this->assertTrue(TenantBusinessFeatures::platformExecutionPartner($company));
    }

    public function test_platform_execution_partner_forced_by_company_email(): void
    {
        Config::set('tenant_features.platform_execution_partner_company_ids', []);
        Config::set('tenant_features.platform_execution_partner_company_emails', ['execution.partner@demo.sa']);

        $company = $this->createCompany([
            'email' => 'Execution.Partner@demo.sa',
        ]);

        $this->assertTrue(TenantBusinessFeatures::platformExecutionPartner($company));
    }
}
