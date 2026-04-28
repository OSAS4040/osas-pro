<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Trace;

final class NullPlatformIntelligenceTraceRecorder implements PlatformIntelligenceTraceRecorderInterface
{
    public function record(PlatformIntelligenceTraceEvent $event): void
    {
        // Foundation default: no noisy persistence.
    }
}
