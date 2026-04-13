<?php

namespace App\Services;

use App\Enums\VatType;

class VatEngine
{
    /**
     * Calculate VAT for a given amount.
     *
     * @param  float   $amount       Base amount (exclusive of VAT)
     * @param  string  $vatType      VatType enum value string
     * @param  bool    $inclusive    If true, amount already includes VAT
     * @return array{base: float, vat: float, total: float, rate: float}
     */
    public function calculate(float $amount, string $vatType = 'standard', bool $inclusive = false): array
    {
        $type = VatType::from($vatType);
        $rate = $type->rate();

        if ($rate === 0.0) {
            return [
                'base'  => round($amount, 4),
                'vat'   => 0.0,
                'total' => round($amount, 4),
                'rate'  => $rate,
            ];
        }

        if ($inclusive) {
            $base = round($amount / (1 + $rate / 100), 4);
            $vat  = round($amount - $base, 4);
        } else {
            $base = round($amount, 4);
            $vat  = round($amount * ($rate / 100), 4);
        }

        return [
            'base'  => $base,
            'vat'   => $vat,
            'total' => round($base + $vat, 4),
            'rate'  => $rate,
        ];
    }

    /**
     * Calculate VAT for invoice items array.
     * Each item: ['unit_price', 'quantity', 'discount_amount'?, 'tax_rate'?, 'vat_type'?]
     */
    public function calculateForItems(array $items): array
    {
        $subtotal  = 0.0;
        $vatTotal  = 0.0;
        $processed = [];

        foreach ($items as $item) {
            $qty      = (float) ($item['quantity'] ?? 1);
            $price    = (float) ($item['unit_price'] ?? 0);
            $discount = (float) ($item['discount_amount'] ?? 0);
            $vatType  = $item['vat_type'] ?? 'standard';

            $lineBase = round($qty * $price - $discount, 4);

            // Override: if explicit tax_rate given, use it directly
            if (isset($item['tax_rate'])) {
                $rate    = (float) $item['tax_rate'];
                $lineVat = round($lineBase * $rate / 100, 4);
            } else {
                $calc    = $this->calculate($lineBase, $vatType);
                $lineVat = $calc['vat'];
                $rate    = $calc['rate'];
            }

            $lineTotal = $lineBase + $lineVat;
            $subtotal += $lineBase;
            $vatTotal += $lineVat;

            $processed[] = array_merge($item, [
                'line_subtotal' => $lineBase,
                'line_vat'      => $lineVat,
                'line_total'    => $lineTotal,
                'tax_rate'      => $rate,
            ]);
        }

        return [
            'items'    => $processed,
            'subtotal' => round($subtotal, 4),
            'vat'      => round($vatTotal, 4),
            'total'    => round($subtotal + $vatTotal, 4),
        ];
    }
}
