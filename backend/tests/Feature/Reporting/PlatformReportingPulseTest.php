<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PlatformReportingPulseTest extends TestCase
{
    private function pulseUrl(): string
    {
        return '/api/v1/reporting/v1/platform/pulse-summary';
    }

    private function dateQuery(): array
    {
        return [
            'from' => now()->subDays(14)->toDateString(),
            'to'   => now()->toDateString(),
        ];
    }

    public function test_non_platform_operator_receives_403(): void
    {
        Config::set('saas.platform_admin_emails', ['ops@platform.example']);

        $tenant = $this->createTenant('owner');

        $res = $this->actingAsUser($tenant['user'])
            ->getJson($this->pulseUrl().'?'.http_build_query($this->dateQuery()));

        $res->assertForbidden()
            ->assertJsonPath('code', 'PLATFORM_ACCESS_ONLY');
        $this->assertArrayNotHasKey('report', $res->json());
    }

    public function test_platform_operator_receives_unified_envelope_and_summary_shape(): void
    {
        Config::set('saas.platform_admin_emails', ['platform-lead@test.sa']);

        $user = $this->createStandalonePlatformOperator('platform-lead@test.sa');

        $this->actingAsUser($user)
            ->getJson($this->pulseUrl().'?'.http_build_query($this->dateQuery()))
            ->assertOk()
            ->assertJsonPath('report.id', 'platform.pulse_summary')
            ->assertJsonPath('report.read_only', true)
            ->assertJsonStructure([
                'report' => ['id', 'version', 'generated_at', 'period', 'filters', 'read_only', 'export'],
                'data' => [
                    'summary' => [
                        'companies_total',
                        'companies_operational',
                        'companies_suspended',
                        'companies_other',
                        'users_total',
                        'customers_total',
                        'branches_total',
                        'subscriptions_total',
                        'tickets_open',
                        'tickets_overdue',
                        'work_orders_in_period',
                    ],
                    'breakdown' => [
                        'by_status',
                        'by_activity',
                        'by_time_period',
                    ],
                ],
                'meta',
                'trace_id',
            ]);
    }

    public function test_company_total_reflects_extra_company_row(): void
    {
        Config::set('saas.platform_admin_emails', ['platform-lead@test.sa']);

        $user = $this->createStandalonePlatformOperator('platform-lead@test.sa');

        $before = $this->actingAsUser($user)
            ->getJson($this->pulseUrl().'?'.http_build_query($this->dateQuery()))
            ->json('data.summary.companies_total');

        $this->createCompany(['name' => 'Standalone Co '.uniqid('', true)]);

        $after = $this->actingAsUser($user)
            ->getJson($this->pulseUrl().'?'.http_build_query($this->dateQuery()))
            ->json('data.summary.companies_total');

        $this->assertSame((int) $before + 1, (int) $after);
    }

    public function test_invalid_date_range_returns_422(): void
    {
        Config::set('saas.platform_admin_emails', ['platform-lead@test.sa']);
        Config::set('reporting.max_date_range_days', 5);

        $user = $this->createStandalonePlatformOperator('platform-lead@test.sa');

        $q = [
            'from' => now()->subDays(20)->toDateString(),
            'to'   => now()->toDateString(),
        ];

        $this->actingAsUser($user)
            ->getJson($this->pulseUrl().'?'.http_build_query($q))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_technician_cannot_access_even_within_tenant(): void
    {
        Config::set('saas.platform_admin_emails', ['ops@platform.example']);

        $tenant = $this->createTenant('technician');

        $this->actingAsUser($tenant['user'])
            ->getJson($this->pulseUrl().'?'.http_build_query($this->dateQuery()))
            ->assertForbidden();
    }
}
