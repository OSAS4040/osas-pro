<?php

namespace App\Intelligence\Events;

final class VehicleCreated extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $vehicleId,
        private readonly ?int $customerId,
        private readonly string $plateNumber,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'VehicleCreated';
    }

    public function aggregateType(): string
    {
        return 'vehicle';
    }

    public function aggregateId(): string
    {
        return (string) $this->vehicleId;
    }

    public function payload(): array
    {
        return [
            'vehicle_id' => $this->vehicleId,
            'customer_id' => $this->customerId,
            'plate_number' => $this->plateNumber,
        ];
    }
}
