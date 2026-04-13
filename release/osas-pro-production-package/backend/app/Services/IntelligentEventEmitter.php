<?php

namespace App\Services;

use App\Intelligence\Contracts\DomainEventInterface;

/**
 * Application-facing entry for emitting intelligence domain events.
 * Delegates to DomainEventRecorder (which swallows failures).
 */
class IntelligentEventEmitter
{
    public function __construct(
        private readonly DomainEventRecorder $recorder,
    ) {}

    public function emit(DomainEventInterface $event): void
    {
        $this->recorder->record($event);
    }
}
