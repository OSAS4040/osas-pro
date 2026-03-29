<?php

namespace App\Intelligence\Contracts;

/**
 * Serializable domain event for the intelligent layer (audit / future automation).
 * No side effects in implementations — payload must be safe to JSON-encode.
 */
interface DomainEventInterface
{
    public function name(): string;

    public function aggregateType(): string;

    public function aggregateId(): string;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** @return array<string, mixed> */
    public function metadata(): array;

    public function eventVersion(): int;
}
