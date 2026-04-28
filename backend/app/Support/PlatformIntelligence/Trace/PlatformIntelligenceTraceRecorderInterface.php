<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Trace;

interface PlatformIntelligenceTraceRecorderInterface
{
    public function record(PlatformIntelligenceTraceEvent $event): void;
}
