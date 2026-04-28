<?php

namespace App\Support\Accounting;

/**
 * Structured context for ledger posting failures (POS / invoice / async repair).
 */
final class LedgerPostingDiagnostics
{
    /**
     * @param  list<array{account_code: string, type: string, amount: float, description?: string}>  $lines
     * @return array<string, mixed>
     */
    public static function fromGlLines(
        string $operationType,
        array $lines,
        array $extra = [],
    ): array {
        $debit = 0.0;
        $credit = 0.0;
        $codes = [];
        foreach ($lines as $line) {
            $code = (string) ($line['account_code'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
            $amt = (float) ($line['amount'] ?? 0);
            if (($line['type'] ?? '') === 'debit') {
                $debit += $amt;
            } elseif (($line['type'] ?? '') === 'credit') {
                $credit += $amt;
            }
        }

        return array_merge([
            'posting_service'  => \App\Services\LedgerService::class,
            'operation_type'   => $operationType,
            'gl_line_count'    => count($lines),
            'account_codes'    => $codes,
            'gl_total_debit'   => round($debit, 4),
            'gl_total_credit'  => round($credit, 4),
            'gl_balanced'      => abs($debit - $credit) < 0.0002,
        ], $extra);
    }
}
