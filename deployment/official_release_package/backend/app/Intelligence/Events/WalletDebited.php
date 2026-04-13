<?php

namespace App\Intelligence\Events;

final class WalletDebited extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $walletTransactionId,
        private readonly int $customerWalletId,
        private readonly float $amount,
        private readonly string $transactionType,
        private readonly ?int $invoiceId,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'WalletDebited';
    }

    public function aggregateType(): string
    {
        return 'wallet_transaction';
    }

    public function aggregateId(): string
    {
        return (string) $this->walletTransactionId;
    }

    public function payload(): array
    {
        return [
            'wallet_transaction_id' => $this->walletTransactionId,
            'customer_wallet_id'    => $this->customerWalletId,
            'amount'                => $this->amount,
            'transaction_type'      => $this->transactionType,
            'invoice_id'            => $this->invoiceId,
        ];
    }
}
