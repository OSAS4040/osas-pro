<?php

namespace App\Services\IntelligentReading;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * استخراج بنود إضافية من نص الفاتورة ومطابقة أسماء تقريبية مع منتجات النظام.
 */
final class InvoiceOcrEnricher
{
    /**
     * @return list<array{description: string, qty: float|null, unit_price: float|null, matched_product_id: int|null, match_score: float, matched: bool}>
     */
    public static function extractLineItems(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) < 4) {
                continue;
            }
            // ضوضاء شائعة في فواتير ZATCA / QR — ليست بنود سلع
            if (preg_match('/\b(QR\s*code|ZATCA|e-invoicing|e\-invoice|TLV|base64|7016|6060020)\b/iu', $line)) {
                continue;
            }
            if (preg_match('/^[\p{P}\p{S}\s£€$]+$/u', $line)) {
                continue;
            }
            if (preg_match('/^(?:صنف|item|description|الوصف|#\s*\d+)/iu', $line)) {
                continue;
            }
            if (preg_match('/^[\d\s.\-]+$/u', $line)) {
                continue;
            }
            $qty = null;
            $price = null;
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:x|×|\*)\s*(\d+(?:[.,]\d+)?)/u', $line, $m)) {
                $qty = (float) str_replace(',', '.', $m[1]);
                $price = (float) str_replace([',', ' '], ['.', ''], $m[2]);
            } elseif (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:qty|الكمية|كمية)?/iu', $line, $m)) {
                $qty = (float) str_replace(',', '.', $m[1]);
            }
            if (preg_match('/(\d+(?:[.,]\d{1,2}))\s*(?:SAR|ريال|ر\.س)/iu', $line, $m)) {
                $price = (float) str_replace([',', ' '], ['.', ''], $m[1]);
            }
            $desc = preg_replace('/\s+/u', ' ', $line);
            $desc = trim((string) $desc);
            if (mb_strlen($desc) < 3) {
                continue;
            }
            $items[] = [
                'description' => Str::limit($desc, 500, ''),
                'qty' => $qty,
                'unit_price' => $price,
                'matched_product_id' => null,
                'match_score' => 0.0,
                'matched' => false,
            ];
        }

        return array_slice($items, 0, 50);
    }

    /**
     * @param  list<array{description: string, qty: float|null, unit_price: float|null, matched_product_id: int|null, match_score: float, matched: bool}>  $items
     * @return list<array{description: string, qty: float|null, unit_price: float|null, matched_product_id: int|null, match_score: float, matched: bool}>
     */
    public static function matchProducts(int $companyId, array $items): array
    {
        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get(['id', 'name', 'name_ar', 'sku']);

        $out = [];
        foreach ($items as $row) {
            $desc = mb_strtolower($row['description']);
            $bestId = null;
            $bestScore = 0.0;
            foreach ($products as $p) {
                foreach ([$p->name, $p->name_ar, $p->sku] as $field) {
                    if (! $field) {
                        continue;
                    }
                    $pct = 0.0;
                    similar_text($desc, mb_strtolower((string) $field), $pct);
                    $norm = $pct / 100;
                    if ($norm > $bestScore) {
                        $bestScore = $norm;
                        $bestId = (int) $p->id;
                    }
                }
            }
            $matched = $bestScore >= 0.45 && $bestId !== null;
            $out[] = array_merge($row, [
                'matched_product_id' => $matched ? $bestId : null,
                'match_score' => round($bestScore, 3),
                'matched' => $matched,
            ]);
        }

        return $out;
    }
}
