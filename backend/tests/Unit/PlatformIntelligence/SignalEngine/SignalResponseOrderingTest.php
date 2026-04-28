<?php

declare(strict_types=1);

namespace Tests\Unit\PlatformIntelligence\SignalEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use App\Support\PlatformIntelligence\SignalEngine\SignalResponseOrdering;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SignalResponseOrderingTest extends TestCase
{
    public function test_orders_by_severity_then_confidence_then_last_seen_then_signal_key(): void
    {
        $t1 = new DateTimeImmutable('2026-01-01T10:00:00Z');
        $t2 = new DateTimeImmutable('2026-01-02T10:00:00Z');
        $b = fn (string $key, PlatformIntelligenceSeverity $sev, float $conf, DateTimeImmutable $ts) => new PlatformSignalContract(
            $key,
            PlatformSignalType::Rule,
            'T',
            'S',
            'W',
            $sev,
            $conf,
            PlatformSignalSourceType::Operations,
            null,
            'scope',
            [],
            [],
            $t1,
            $ts,
            'next',
            [],
            null,
            'cid',
        );

        $signals = [
            $b('sig.z', PlatformIntelligenceSeverity::Low, 0.9, $t1),
            $b('sig.a', PlatformIntelligenceSeverity::High, 0.5, $t1),
            $b('sig.m', PlatformIntelligenceSeverity::High, 0.8, $t1),
            $b('sig.n', PlatformIntelligenceSeverity::High, 0.8, $t2),
        ];
        $out = SignalResponseOrdering::sortStable($signals);
        $keys = array_map(static fn (PlatformSignalContract $s) => $s->signal_key, $out);
        $this->assertSame(['sig.n', 'sig.m', 'sig.a', 'sig.z'], $keys);
    }
}
