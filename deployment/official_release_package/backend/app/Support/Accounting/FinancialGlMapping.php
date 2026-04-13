<?php

namespace App\Support\Accounting;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Façade for journal line templates used with LedgerService::post.
 * Wallet events delegate to WalletGlMapping (single source of truth for wallet GL lines).
 */
final class FinancialGlMapping
{
    public const WALLET_TOP_UP = WalletGlMapping::EVENT_TOP_UP;

    public const WALLET_TRANSFER = WalletGlMapping::EVENT_TRANSFER;

    public const WALLET_DEBIT = WalletGlMapping::EVENT_DEBIT;

    public const WALLET_CREDIT_DEBIT = WalletGlMapping::EVENT_CREDIT_DEBIT;

    /**
     * @param  array{debit?: string, credit?: string}  $descriptions
     * @return list<array{account_code: string, type: 'debit'|'credit', amount: float, description?: string}>
     */
    public static function walletLines(string $walletEventType, float $amount, array $descriptions = []): array
    {
        return WalletGlMapping::lines($walletEventType, $amount, $descriptions);
    }

    /**
     * Sale journal for generic invoice creation (accrual: 1200, paid: 1010).
     * Net revenue only on 4100 (subtotal − discount); no COGS/discount expense line.
     *
     * @return list<array{account_code: string, type: 'debit'|'credit', amount: float, description?: string}>
     */
    public static function linesForSaleInvoice(Invoice $invoice): array
    {
        $receivableCode = $invoice->status === InvoiceStatus::Paid ? '1010' : '1200';

        $lines = [[
            'account_code' => $receivableCode,
            'type'         => 'debit',
            'amount'       => (float) $invoice->total,
            'description'  => "Invoice {$invoice->invoice_number}",
        ]];

        $netRevenue = max(0.0, (float) $invoice->subtotal - (float) $invoice->discount_amount);

        if ($netRevenue > 0.0001) {
            $lines[] = [
                'account_code' => '4100',
                'type'         => 'credit',
                'amount'       => $netRevenue,
                'description'  => "Revenue (net) - {$invoice->invoice_number}",
            ];
        }

        if ((float) $invoice->tax_amount > 0.0001) {
            $lines[] = [
                'account_code' => '2300',
                'type'         => 'credit',
                'amount'       => (float) $invoice->tax_amount,
                'description'  => "VAT Output - {$invoice->invoice_number}",
            ];
        }

        return $lines;
    }

    /**
     * POS sale: cash debit; net revenue and VAT credits (same net revenue rule as sale_invoice).
     *
     * @return list<array{account_code: string, type: 'debit'|'credit', amount: float, description?: string}>
     */
    public static function linesForPosSale(Invoice $invoice): array
    {
        $lines = [[
            'account_code' => '1010',
            'type'         => 'debit',
            'amount'       => (float) $invoice->total,
            'description'  => "POS Sale {$invoice->invoice_number}",
        ]];

        $netRevenue = max(0.0, (float) $invoice->subtotal - (float) $invoice->discount_amount);

        if ($netRevenue > 0.0001) {
            $lines[] = [
                'account_code' => '4100',
                'type'         => 'credit',
                'amount'       => $netRevenue,
                'description'  => "Revenue (net) - {$invoice->invoice_number}",
            ];
        }

        if ((float) $invoice->tax_amount > 0.0001) {
            $lines[] = [
                'account_code' => '2300',
                'type'         => 'credit',
                'amount'       => (float) $invoice->tax_amount,
                'description'  => "VAT Output - {$invoice->invoice_number}",
            ];
        }

        return $lines;
    }
}
