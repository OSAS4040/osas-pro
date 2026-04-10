<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * TLV payload for ZATCA e-invoice QR (Phase 2 simplified), matching frontend {@see generateZatcaTLV}.
 */
final class ZatcaInvoiceTlv
{
    public static function base64Payload(Invoice $invoice, ?Company $company, string $sellerNameAr): string
    {
        $co = $company;
        $vat = trim((string) ($co?->tax_number ?? ''));
        if ($vat === '') {
            $vat = '000000000000000';
        }

        $issued = $invoice->issued_at ?? $invoice->created_at;
        $invoiceDate = $issued
            ? Carbon::parse($issued)->toIso8601String()
            : Carbon::now()->toIso8601String();

        $parts = [
            self::encodeTlv(1, $sellerNameAr),
            self::encodeTlv(2, $vat),
            self::encodeTlv(3, $invoiceDate),
            self::encodeTlv(4, number_format((float) $invoice->total, 2, '.', '')),
            self::encodeTlv(5, number_format((float) $invoice->tax_amount, 2, '.', '')),
        ];

        return base64_encode(implode('', $parts));
    }

    private static function encodeTlv(int $tag, string $value): string
    {
        $valueBytes = $value;
        $len = strlen($valueBytes);

        return chr($tag).chr($len).$valueBytes;
    }
}
