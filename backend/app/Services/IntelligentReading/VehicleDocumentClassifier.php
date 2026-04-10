<?php

namespace App\Services\IntelligentReading;

/**
 * تصنيف أولي لمستندات المركبة من النص المستخرج + استخراج تواريخ مرجعية.
 */
final class VehicleDocumentClassifier
{
    /**
     * @return array{type: string, title: string, reference: string|null, issue_date: string|null, expiry_date: string|null, confidence: string}
     */
    public static function classify(string $textUpper): array
    {
        $t = $textUpper;
        $type = 'other';
        $title = 'مستند مرفوع';

        if (preg_match('/تأمين|INSURANCE|TPL|شامل|INS\./u', $t)) {
            $type = 'insurance';
            $title = 'وثيقة تأمين';
        } elseif (preg_match('/استمارة|REGISTRATION|MVPI|الفحص الدوري/u', $t)) {
            $type = 'registration';
            $title = 'استمارة / تسجيل';
        } elseif (preg_match('/فحص|INSPECTION|معاينة/u', $t)) {
            $type = 'technical';
            $title = 'فحص فني';
        } elseif (preg_match('/رخصة|LICENSE|قيادة/u', $t)) {
            $type = 'license';
            $title = 'رخصة سير';
        }

        $reference = null;
        if (preg_match('/(?:REF|رقم|NO\.?|#)\s*[:\s]*([A-Z0-9\-\/]{4,32})/i', $t, $m)) {
            $reference = trim($m[1]);
        }

        $issue = self::firstDate($t);
        $expiry = self::expiryDate($t) ?? self::lastDate($t);

        $confidence = 'low';
        if ($type !== 'other' && ($expiry || $reference)) {
            $confidence = 'medium';
        }
        if ($type !== 'other' && $expiry && $reference) {
            $confidence = 'high';
        }

        return [
            'type' => $type,
            'title' => $title,
            'reference' => $reference,
            'issue_date' => $issue,
            'expiry_date' => $expiry,
            'confidence' => $confidence,
        ];
    }

    private static function firstDate(string $t): ?string
    {
        if (preg_match('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $t, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        if (preg_match('/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/', $t, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    private static function expiryDate(string $t): ?string
    {
        if (preg_match('/(?:EXPIR|انتهاء|VALID\s*UNTIL|صالح\s*حتى|ينتهي)[:\s]*(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})/i', $t, $m)) {
            $d = str_replace('/', '-', $m[1]);
            $p = explode('-', $d);

            return sprintf('%04d-%02d-%02d', (int) $p[0], (int) $p[1], (int) $p[2]);
        }

        return null;
    }

    private static function lastDate(string $t): ?string
    {
        preg_match_all('/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $t, $matches, PREG_SET_ORDER);
        if (! $matches) {
            return null;
        }
        $last = end($matches);
        if (! $last) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $last[1], (int) $last[2], (int) $last[3]);
    }
}
