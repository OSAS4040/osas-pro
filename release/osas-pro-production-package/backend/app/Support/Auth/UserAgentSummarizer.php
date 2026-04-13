<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * Short, non-identifying label for session lists (not a full UA parser).
 */
final class UserAgentSummarizer
{
    public static function summarize(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return 'Unknown';
        }

        if (preg_match('/Edg\/[\d.]+/i', $ua) === 1) {
            return 'Microsoft Edge';
        }
        if (stripos($ua, 'Chrome') !== false || stripos($ua, 'CriOS') !== false) {
            return 'Chrome';
        }
        if (stripos($ua, 'Firefox') !== false) {
            return 'Firefox';
        }
        if (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
            return 'Safari';
        }

        return strlen($ua) > 72 ? substr($ua, 0, 69).'…' : $ua;
    }
}
