<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

final class PlatformAdminOverviewTest extends TestCase
{
    public function test_tenant_user_cannot_read_admin_overview(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->getJson('/api/v1/admin/overview')
            ->assertForbidden();
    }

    public function test_platform_operator_receives_overview_payload(): void
    {
        $user = $this->createStandalonePlatformOperator('overview@platform.test');

        $res = $this->actingAsUser($user)
            ->getJson('/api/v1/admin/overview');

        $res->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'generated_at',
                    'cache',
                    'definitions',
                    'kpis' => [
                        'total_companies',
                        'active_companies',
                        'low_activity_companies',
                        'trial_companies',
                        'churn_risk_companies',
                        'total_users',
                        'subscriptions_active',
                        'estimated_mrr',
                    ],
                    'trends' => ['companies_growth', 'activity_trend', 'subscription_trend'],
                    'distribution' => ['by_plan', 'by_status'],
                    'activity' => ['most_active_companies', 'least_active_companies', 'avg_activity_score'],
                    'health',
                    'alerts',
                    'companies_requiring_attention',
                    'insights',
                ],
                'trace_id',
            ]);
    }
}
