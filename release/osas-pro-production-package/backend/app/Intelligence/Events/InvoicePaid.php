<?php

namespace App\Intelligence\Events;

/**
 * Emitted after a payment is successfully recorded against an invoice (core flow already completed).
 */
final class InvoicePaid extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $invoiceId,
        private readonly int $paymentId,
        private readonly float $amount,
        private readonly string $method,
        private readonly string $invoiceStatus,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'InvoicePaid';
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
            'payment_id' => $this->paymentId,
            'amount' => $this->amount,
            'method' => $this->method,
            'invoice_status' => $this->invoiceStatus,
        ];
    }
}
