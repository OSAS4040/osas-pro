<?php

declare(strict_types=1);

namespace App\Reporting\Export;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Read-only reporting exports (WAVE 2 / PR12). Disabled by default.
 */
final class ReportingExportGate
{
    public static function ensureEnabled(): void
    {
        if (! (bool) config('reporting.export.enabled', false)) {
            throw new NotFoundHttpException('Reporting export is not enabled.');
        }
    }

    /**
     * @return list<string>
     */
    public static function formats(): array
    {
        $raw = config('reporting.export.formats_supported', ['csv']);

        return is_array($raw) ? array_values(array_filter(array_map('strtolower', array_map('strval', $raw)))) : ['csv'];
    }

    public static function isAllowedFormat(string $format): bool
    {
        return in_array(strtolower($format), self::formats(), true);
    }
}
