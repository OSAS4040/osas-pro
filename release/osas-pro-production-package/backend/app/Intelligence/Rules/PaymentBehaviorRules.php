<?php

declare(strict_types=1);

namespace App\Intelligence\Rules;

/**
 * Payment behaviour labels (financial visibility enforced by caller).
 */
final class PaymentBehaviorRules
{
    public static function fromInvoiceSignals(int $overdueInvoices, int $staleUnpaidInvoices, bool $financialIncluded): string
    {
        if (! $financialIncluded) {
            return 'unknown';
        }
        if ($overdueInvoices > 0) {
            return 'risky';
        }
        if ($staleUnpaidInvoices > 0) {
            return 'delayed';
        }

        return 'good';
    }
}
