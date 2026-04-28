<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\CommandPrioritization;

use App\Support\PlatformIntelligence\CommandPrioritization\CommandSurfacePrioritizer;
use PHPUnit\Framework\TestCase;

final class CommandSurfacePrioritizerTest extends TestCase
{
    public function test_stable_ordering_tie_breaker_by_incident_key(): void
    {
        $rows = [
            ['incident_key' => 'b', 'status' => 'open', 'severity' => 'high', 'escalation_state' => 'none', 'last_status_change_at' => '2026-01-01T00:00:00Z'],
            ['incident_key' => 'a', 'status' => 'open', 'severity' => 'high', 'escalation_state' => 'none', 'last_status_change_at' => '2026-01-01T00:00:00Z'],
        ];
        $out = CommandSurfacePrioritizer::sortIncidentsStable($rows);
        // عند تساوي النقاط: ربط ثابت تصاعدي على incident_key (strcmp)
        $this->assertSame(['a', 'b'], array_column($out, 'incident_key'));
    }

    public function test_escalated_ranks_above_same_severity_open(): void
    {
        $rows = [
            ['incident_key' => 'x1', 'status' => 'open', 'severity' => 'medium', 'escalation_state' => 'none', 'last_status_change_at' => null],
            ['incident_key' => 'x2', 'status' => 'open', 'severity' => 'medium', 'escalation_state' => 'escalated', 'last_status_change_at' => null],
        ];
        $out = CommandSurfacePrioritizer::sortIncidentsStable($rows);
        $this->assertSame('x2', $out[0]['incident_key']);
    }
}
