<?php

namespace App\Intelligence\Events;

final class InvoiceCreated extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $invoiceId,
        private readonly string $invoiceNumber,
        private readonly string $status,
        private readonly float $total,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'InvoiceCreated';
    }

    public function aggregateType(): string
    {
        return 'invoice';
    }

    public function aggregateId(): string
    {
        return (string) $this->invoiceId;
    }

    public function payload(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'invoice_number' => $this->invoiceNumber,
            'status' => $this->status,
            'total' => $this->total,
        ];
    }
}
