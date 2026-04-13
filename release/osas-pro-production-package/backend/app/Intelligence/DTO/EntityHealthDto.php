<?php

declare(strict_types=1);

namespace App\Intelligence\DTO;

/**
 * Canonical health label for any operational entity.
 */
final readonly class EntityHealthDto
{
    public function __construct(
        public string $healthStatus,
    ) {}

    public function toArray(): array
    {
        return ['health_status' => $this->healthStatus];
    }
}
