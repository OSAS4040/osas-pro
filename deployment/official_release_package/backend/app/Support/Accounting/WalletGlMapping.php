<?php

namespace App\Support\Accounting;

/**
 * Standardized GL lines for wallet-related LedgerService posts (amount must be > 0).
 */
final class WalletGlMapping
{
    public const EVENT_TOP_UP = 'wallet_top_up';

    public const EVENT_TRANSFER = 'wallet_transfer';

    public const EVENT_DEBIT = 'wallet_debit';

    public const EVENT_CREDIT_DEBIT = 'wallet_credit_debit';

    /**
     * @param  array{debit?: string, credit?: string}  $descriptions  Optional line descriptions
     * @return list<array{account_code: string, type: 'debit'|'credit', amount: float, description?: string}>
     */
    public static function lines(string $eventType, float $amount, array $descriptions = []): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Wallet GL mapping requires a positive amount.');
        }

        $debitDesc  = $descriptions['debit'] ?? null;
        $creditDesc = $descriptions['credit'] ?? null;

        return match ($eventType) {
            self::EVENT_TOP_UP => [
                ['account_code' => '1020', 'type' => 'debit', 'amount' => $amount, 'description' => $debitDesc ?? 'Cash received for fleet wallet'],
                ['account_code' => '2410', 'type' => 'credit', 'amount' => $amount, 'description' => $creditDesc ?? 'Fleet main wallet deposit'],
            ],
            self::EVENT_TRANSFER => [
                ['account_code' => '2410', 'type' => 'debit', 'amount' => $amount, 'description' => $debitDesc ?? 'Fleet main wallet out'],
                ['account_code' => '2420', 'type' => 'credit', 'amount' => $amount, 'description' => $creditDesc ?? 'Vehicle wallet in'],
            ],
            self::EVENT_DEBIT => [
                ['account_code' => '2420', 'type' => 'debit', 'amount' => $amount, 'description' => $debitDesc ?? 'Vehicle wallet charged'],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => $amount, 'description' => $creditDesc ?? 'Service revenue'],
            ],
            self::EVENT_CREDIT_DEBIT => [
                ['account_code' => '2420', 'type' => 'debit', 'amount' => $amount, 'description' => $debitDesc ?? 'Vehicle wallet credit debit'],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => $amount, 'description' => $creditDesc ?? 'Service revenue (credit)'],
            ],
            default => throw new \InvalidArgumentException("Unknown wallet GL event: {$eventType}"),
        };
    }
}
