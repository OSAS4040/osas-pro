<?php

namespace App\Intelligence\Events;

final class CustomerCreated extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $customerId,
        private readonly string $customerUuid,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'CustomerCreated';
    }

    public function aggregateType(): string
    {
        return 'customer';
    }

    public function aggregateId(): string
    {
        return (string) $this->customerId;
    }

    public function payload(): array
    {
        return [
            'customer_id'   => $this->customerId,
            'customer_uuid' => $this->customerUuid,
        ];
    }
}
