<?php

namespace App\Support\Auth;

/**
 * Server-side phone normalization for unified login (identifier).
 * Conservative: produces digit variants commonly stored in users.phone.
 */
final class PhoneNormalizer
{
    /**
     * Strip to digits only (no leading + processing beyond removing non-digits).
     */
    public static function digitsOnly(string $raw): string
    {
        $s = preg_replace('/[^\d]/u', '', $raw) ?? '';

        return $s;
    }

    /**
     * @return list<string> Unique digit strings to try against DB normalized phone.
     */
    public static function comparisonVariants(string $raw): array
    {
        $digits = self::digitsOnly($raw);
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];

        // Local KSA mobile often stored as 05xxxxxxxx vs 9665xxxxxxxx
        if (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $variants[] = '966'.substr($digits, 1);
        }
        if (str_starts_with($digits, '966')) {
            $local = '0'.substr($digits, 3);
            if (strlen($local) >= 10) {
                $variants[] = $local;
            }
        }
        if (! str_starts_with($digits, '966') && strlen($digits) === 9 && $digits[0] === '5') {
            $variants[] = '966'.$digits;
            $variants[] = '0'.$digits;
        }

        $out = [];
        foreach ($variants as $v) {
            if ($v !== '' && ! in_array($v, $out, true)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * Prefer a stable international-ish digit form for storage (e.g. 9665… for KSA mobiles).
     */
    public static function normalizeForStorage(string $raw): string
    {
        $variants = self::comparisonVariants($raw);
        foreach ($variants as $v) {
            if (str_starts_with($v, '966') && strlen($v) >= 12) {
                return $v;
            }
        }

        return $variants[0] ?? self::digitsOnly($raw);
    }
}
