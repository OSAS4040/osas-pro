<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Registers Noto Naskh Arabic with Dompdf. Logs under event {@see self::LOG_EVENT} for filtering in production.
 */
final class DompdfArabicFont
{
    public const LOG_EVENT = 'dompdf_arabic_font';

    public static function registerNotoNaskhArabic(
        PdfWrapper $pdf,
        string $logContext = 'DompdfArabicFont',
        bool $logOnFailure = true,
    ): bool {
        $fontDir = storage_path('fonts');
        if (! self::prepareFontCacheDirectory($fontDir, $logContext, $logOnFailure)) {
            return false;
        }

        $path = resource_path('fonts/NotoNaskhArabic-Regular.ttf');
        if (! is_readable($path)) {
            if ($logOnFailure) {
                self::logUnavailable($logContext, 'noto_file_missing', [
                    'path' => $path,
                    'remediation' => DompdfArabicPdfSupport::REMEDIATION_FONT_FILES,
                ]);
            }

            return false;
        }

        $real = realpath($path);
        if ($real === false) {
            if ($logOnFailure) {
                self::logUnavailable($logContext, 'noto_realpath_failed', [
                    'path' => $path,
                    'remediation' => DompdfArabicPdfSupport::REMEDIATION_FONT_FILES,
                ]);
            }

            return false;
        }

        $regularUri = self::absolutePathToFileUri($real);
        $boldPath = resource_path('fonts/NotoNaskhArabic-Bold.ttf');
        $boldUri = is_readable($boldPath)
            ? self::absolutePathToFileUri((string) realpath($boldPath))
            : $regularUri;

        $metrics = $pdf->getDomPDF()->getFontMetrics();
        $okNormal = $metrics->registerFont([
            'family' => 'Noto Naskh Arabic',
            'weight' => 'normal',
            'style' => 'normal',
        ], $regularUri);
        $okBold = $metrics->registerFont([
            'family' => 'Noto Naskh Arabic',
            'weight' => 'bold',
            'style' => 'normal',
        ], $boldUri);

        if (! $okNormal || ! $okBold) {
            if ($logOnFailure) {
                self::logUnavailable($logContext, 'dompdf_register_font_failed', [
                    'regular_ok' => $okNormal,
                    'bold_ok' => $okBold,
                    'font_path' => $real,
                    'remediation' => DompdfArabicPdfSupport::REMEDIATION_REGISTER,
                ]);
            }

            return false;
        }

        $pdf->setOption('defaultFont', 'noto naskh arabic');

        return true;
    }

    private static function prepareFontCacheDirectory(string $fontDir, string $logContext, bool $logOnFailure): bool
    {
        try {
            if (! File::exists($fontDir)) {
                File::makeDirectory($fontDir, 0775, true);
            }
            if (! is_dir($fontDir) || ! is_writable($fontDir)) {
                if ($logOnFailure) {
                    self::logUnavailable($logContext, 'font_cache_not_writable', [
                        'font_dir' => $fontDir,
                        'remediation' => DompdfArabicPdfSupport::REMEDIATION_STORAGE,
                    ]);
                }

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            if ($logOnFailure) {
                self::logUnavailable($logContext, 'font_cache_prepare_failed', [
                    'font_dir' => $fontDir,
                    'error' => $e->getMessage(),
                    'remediation' => DompdfArabicPdfSupport::REMEDIATION_STORAGE,
                ]);
            }

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function logUnavailable(string $serviceContext, string $reason, array $context): void
    {
        Log::warning(
            '[dompdf:arabic-font] Arabic PDF font unavailable — '.$reason.' — PDFs may show missing or tofu glyphs for Arabic.',
            array_merge([
                'event' => self::LOG_EVENT,
                'reason' => $reason,
                'service' => $serviceContext,
            ], $context)
        );
    }

    private static function absolutePathToFileUri(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        if (preg_match('#^([a-zA-Z]):/#', $normalized)) {
            return 'file:///'.$normalized;
        }

        return 'file://'.$normalized;
    }
}
