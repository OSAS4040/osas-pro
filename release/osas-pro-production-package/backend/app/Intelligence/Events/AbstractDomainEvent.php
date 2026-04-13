<?php

namespace App\Intelligence\Events;

use App\Intelligence\Contracts\DomainEventInterface;

abstract class AbstractDomainEvent implements DomainEventInterface
{
    public function __construct(
        protected readonly ?int $companyId,
        protected readonly ?int $branchId,
        protected readonly ?int $causedByUserId,
        protected readonly ?string $sourceContext = null,
    ) {}

    public function metadata(): array
    {
        return array_filter([
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'caused_by_user_id' => $this->causedByUserId,
            'source_context' => $this->sourceContext,
        ], fn ($v) => $v !== null);
    }

    public function eventVersion(): int
    {
        return 1;
    }
}
