<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Display-safe IP summary (no full address in list UIs by default).
 */
final class IpAddressSummarizer
{
    public static function summarize(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0].'.'.$parts[1].'.'.$parts[2].'.•••';
            }
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $collapsed = preg_replace('/^([0-9a-f]{0,4}:){3}[0-9a-f:]+$/i', '$1…', $ip) ?? $ip;

            return strlen($collapsed) > 32 ? substr($collapsed, 0, 29).'…' : $collapsed;
        }

        return strlen($ip) > 24 ? substr($ip, 0, 21).'…' : $ip;
    }
}
