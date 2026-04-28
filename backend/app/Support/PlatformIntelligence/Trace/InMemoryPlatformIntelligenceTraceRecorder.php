<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Trace;

/**
 * @phpstan-type StoredEvent = array{event: PlatformIntelligenceTraceEvent}
 */
final class InMemoryPlatformIntelligenceTraceRecorder implements PlatformIntelligenceTraceRecorderInterface
{
    /** @var list<PlatformIntelligenceTraceEvent> */
    private array $events = [];

    public function record(PlatformIntelligenceTraceEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<PlatformIntelligenceTraceEvent>
     */
    public function all(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
