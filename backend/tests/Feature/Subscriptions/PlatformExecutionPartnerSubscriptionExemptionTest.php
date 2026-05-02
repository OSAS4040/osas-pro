<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformExecutionPartnerSubscriptionExemptionTest extends TestCase
{
    use RefreshDatabase;

    private function enablePlatformExecutionPartner(Company $company): void
    {
        $settings = is_array($company->settings) ? $company->settings : [];
        $profile = is_array($settings['business_profile'] ?? null) ? $settings['business_profile'] : [];
        $matrix = is_array($profile['feature_matrix'] ?? null) ? $profile['feature_matrix'] : [];
        $matrix['platform_execution_partner'] = true;
        $profile['feature_matrix'] = $matrix;
        $profile['business_type'] = $profile['business_type'] ?? 'service_center';
        $settings['business_profile'] = $profile;
        $company->update(['settings' => $settings]);
    }

    public function test_execution_partner_passes_subscription_middleware_without_subscription_row(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.sales.total_revenue', 0)
            ->assertJsonPath('data.receivables.total_outstanding', 0)
            ->assertJsonPath('data.customers.new_in_period', 0);
    }

    public function test_execution_partner_financial_reports_are_forbidden(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/reports/sales')
            ->assertForbidden()
            ->assertJsonPath('code', 'EXECUTION_PARTNER_FINANCIAL_REPORTS_DISABLED');

        $this->actingAsUser($t['user'])
            ->getJson('/api/v1/reports/kpi')
            ->assertOk()
            ->assertJsonPath('data.total_sales', 0)
            ->assertJsonPath('data.invoice_count', 0);
    }

    public function test_execution_partner_can_create_user_without_subscription_quota(): void
    {
        $t = $this->createTenant();
        $t['subscription']->delete();
        $this->enablePlatformExecutionPartner($t['company']->fresh());

        $email = 'extra_staff_'.Str::random(6).'@test.sa';

        $this->actingAsUser($t['user'])
            ->postJson('/api/v1/users', [
                'name' => 'Extra Staff',
                'email' => $email,
                'password' => 'Password123!',
                'role' => 'staff',
                'branch_id' => $t['branch']->id,
            ])
            ->assertCreated();
    }
}
