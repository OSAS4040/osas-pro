<?php

namespace App\Services\IntelligentReading;

/**
 * تطبيع لوحات مرورية سعودية إلى صيغة موحدة للمطابقة والبحث.
 */
final class KsaPlateNormalizer
{
    private const AR_DIG = '٠١٢٣٤٥٦٧٨٩';

    private const EN_DIG = '0123456789';

    /**
     * @return array{display: string, compact: string, letters: string, digits: string}|null
     */
    public static function normalize(string $input): ?array
    {
        $s = trim($input);
        if ($s === '') {
            return null;
        }
        $s = strtoupper($s);
        $s = preg_replace('/[\s\-_\x{200f}\x{200e}،,]/u', '', $s) ?? $s;
        $s = self::westernizeDigits($s);
        $s = preg_replace('/[^A-Z0-9\x{0600}-\x{06FF}]/u', '', $s) ?? $s;
        $s = self::mapArabicPlateLettersToLatin($s);

        if (preg_match('/^([A-Z]{3})(\d{4})$/', $s, $m)) {
            return self::pack($m[1], $m[2]);
        }
        if (preg_match('/([A-Z]{3})\D*(\d{4})/', $s, $m)) {
            return self::pack($m[1], $m[2]);
        }

        return null;
    }

    private static function pack(string $letters, string $digits): array
    {
        $L = substr($letters, 0, 3);
        $D = substr($digits, 0, 4);

        return [
            'display' => "{$L} {$D}",
            'compact' => "{$L}{$D}",
            'letters' => $L,
            'digits' => $D,
        ];
    }

    private static function westernizeDigits(string $s): string
    {
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($s, $i, 1);
            $p = mb_strpos(self::AR_DIG, $c);
            $out .= $p !== false ? self::EN_DIG[$p] : $c;
        }

        return $out;
    }

    /** تحويل أحرف عربية شائعة في مخرجات OCR إلى لاتينية قبل المطابقة */
    private static function mapArabicPlateLettersToLatin(string $s): string
    {
        static $map = [
            'أ' => 'A', 'ا' => 'A', 'ب' => 'B', 'ت' => 'T', 'ث' => 'X', 'ج' => 'G', 'ح' => 'J', 'خ' => 'K',
            'د' => 'D', 'ذ' => 'D', 'ر' => 'R', 'ز' => 'Z', 'س' => 'S', 'ش' => 'S', 'ص' => 'X', 'ض' => 'D',
            'ط' => 'T', 'ظ' => 'Z', 'ع' => 'E', 'غ' => 'G', 'ف' => 'F', 'ق' => 'Q', 'ك' => 'K', 'ل' => 'L',
            'م' => 'M', 'ن' => 'N', 'ه' => 'H', 'و' => 'W', 'ى' => 'D', 'ي' => 'V',
        ];
        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($s, $i, 1);
            if (isset($map[$c])) {
                $out .= $map[$c];
            } elseif (preg_match('/[A-Z0-9]/', $c)) {
                $out .= $c;
            }
        }

        return $out;
    }
}
