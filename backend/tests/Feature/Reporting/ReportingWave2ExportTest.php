<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class ReportingWave2ExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('reporting.export.enabled', false);
    }

    public function test_export_returns_404_when_disabled(): void
    {
        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->get('/api/v1/reporting/v1/operations/work-order-summary/export?'.http_build_query([
                'format' => 'csv',
                'from' => now()->subDays(3)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertNotFound();
    }

    public function test_work_order_summary_csv_export_when_enabled(): void
    {
        Config::set('reporting.export.enabled', true);
        Config::set('reporting.export.formats_supported', ['csv', 'xlsx', 'pdf']);

        $tenant = $this->createTenant('owner');

        $response = $this->actingAsUser($tenant['user'])
            ->get('/api/v1/reporting/v1/operations/work-order-summary/export?'.http_build_query([
                'format' => 'csv',
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ]));

        $response->assertOk();
        $ct = (string) $response->headers->get('Content-Type');
        $this->assertTrue($ct === '' || str_contains($ct, 'text/csv') || str_contains($ct, 'octet-stream'));
        $disp = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('operations-work-order-summary', $disp);
        $body = $response->streamedContent();
        $this->assertStringContainsString('report.id', $body);
    }

    public function test_export_rejects_unknown_format(): void
    {
        Config::set('reporting.export.enabled', true);
        Config::set('reporting.export.formats_supported', ['csv']);

        $tenant = $this->createTenant('owner');

        $this->actingAsUser($tenant['user'])
            ->get('/api/v1/reporting/v1/operations/work-order-summary/export?'.http_build_query([
                'format' => 'xlsx',
                'from' => now()->subDays(3)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertUnprocessable();
    }

    public function test_technician_forbidden_on_export_route(): void
    {
        Config::set('reporting.export.enabled', true);
        Config::set('reporting.export.formats_supported', ['csv']);

        $tenant = $this->createTenant('technician');

        $this->actingAsUser($tenant['user'])
            ->get('/api/v1/reporting/v1/operations/work-order-summary/export?'.http_build_query([
                'format' => 'csv',
                'from' => now()->subDays(3)->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertForbidden();
    }
}
