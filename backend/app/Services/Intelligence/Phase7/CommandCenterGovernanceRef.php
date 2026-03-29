<?php

namespace App\Services\Intelligence\Phase7;

use Illuminate\Support\Str;

/**
 * Server-signed opaque governance_ref for command-center items. Clients cannot forge refs.
 */
final class CommandCenterGovernanceRef
{
    private const VERSION = 1;

    /**
     * Stable fingerprint for signal context (same item + zone + window + snapshot → same fp).
     */
    public static function signalFingerprint(
        string $itemId,
        string $source,
        string $zone,
        string $title,
        string $severity,
    ): string {
        $payload = implode("\0", [$itemId, $source, $zone, $title, $severity]);

        return substr(hash('sha256', $payload), 0, 32);
    }

    /**
     * Snapshot time is intentionally NOT part of the ref so the same logical item in a window
     * keeps one stable ref across command-center refreshes; audit rows store timestamps separately.
     */
    public static function encode(
        int $companyId,
        string $zone,
        string $source,
        string $itemId,
        string $windowFrom,
        string $windowTo,
        string $signalFingerprint,
        string $titleSnapshot,
        string $severitySnapshot,
    ): string {
        $payload = [
            'v'  => self::VERSION,
            'c'  => $companyId,
            'z'  => $zone,
            's'  => $source,
            'i'  => $itemId,
            'wf' => $windowFrom,
            'wt' => $windowTo,
            'fp' => $signalFingerprint,
            'ti' => Str::limit($titleSnapshot, 500, ''),
            'se' => Str::limit($severitySnapshot, 32, ''),
        ];
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $sig  = hash_hmac('sha256', $json, self::key(), true);

        return 'v1.'.self::b64urlEncode($json).'.'.self::b64urlEncode($sig);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function verify(string $token): ?array
    {
        if (! str_starts_with($token, 'v1.')) {
            return null;
        }
        $rest = substr($token, 3);
        $dot  = strrpos($rest, '.');
        if ($dot === false) {
            return null;
        }
        $b64Payload = substr($rest, 0, $dot);
        $b64Sig     = substr($rest, $dot + 1);
        $json       = self::b64urlDecode($b64Payload);
        $sig        = self::b64urlDecode($b64Sig);
        if ($json === '' || $sig === '') {
            return null;
        }
        $expected = hash_hmac('sha256', $json, self::key(), true);
        if (! hash_equals($expected, $sig)) {
            return null;
        }
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($data) || (int) ($data['v'] ?? 0) !== self::VERSION) {
            return null;
        }

        return $data;
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        return hash('sha256', 'osas.intelligence.governance_ref.v1.'.$key, true);
    }

    private static function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $b64): string
    {
        $padded = strtr($b64, '-_', '+/');
        $pad    = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($padded, true);

        return $out === false ? '' : $out;
    }
}
