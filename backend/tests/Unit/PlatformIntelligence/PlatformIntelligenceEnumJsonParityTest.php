<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence;

use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use PHPUnit\Framework\TestCase;

/**
 * Prevents silent drift between PHP backed enums and the canonical JSON fixture
 * (also asserted from the frontend test suite).
 */
final class PlatformIntelligenceEnumJsonParityTest extends TestCase
{
    public function test_php_enum_string_values_match_canonical_json_fixture(): void
    {
        $path = realpath(__DIR__.'/../../fixtures/platform_intelligence_canonical_enum_values.json');
        $this->assertNotFalse($path);
        $raw = (string) file_get_contents($path);
        $this->assertNotSame('', $raw);
        /** @var array<string, list<string>> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($data['severity'], PlatformIntelligenceSeverity::values());
        $this->assertSame($data['incident_status'], PlatformIncidentStatus::values());
        $this->assertSame($data['ownership_state'], PlatformIncidentOwnershipState::values());
        $this->assertSame($data['escalation_state'], PlatformIncidentEscalationState::values());
        $this->assertSame($data['decision_type'], PlatformDecisionType::values());
        $this->assertSame($data['signal_source_type'], PlatformSignalSourceType::values());
        $this->assertSame($data['signal_type'], PlatformSignalType::values());
    }
}
