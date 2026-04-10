<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DompdfArabicFontDiagnostics;
use App\Support\DompdfArabicPdfSupport;
use Illuminate\Console\Command;

/**
 * Verifies Noto Naskh Arabic + storage/fonts for Dompdf (Arabic PDF quality).
 */
final class CheckDompdfArabicFontCommand extends Command
{
    protected $signature = 'dompdf:check-arabic-font {--fail : Exit with code 1 if checks fail (for CI/deploy gates)}';

    protected $description = 'Check Dompdf Arabic font setup (storage/fonts, Noto TTF files, registerFont)';

    public function handle(): int
    {
        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $d = DompdfArabicFontDiagnostics::run($pdf);

        $this->line('storage/fonts: '.$d['storage_fonts_dir']);
        $this->line('  exists: '.($d['storage_fonts_exists'] ? 'yes' : 'no'));
        $this->line('  writable: '.($d['storage_fonts_writable'] ? 'yes' : 'no'));
        $this->newLine();
        $this->line('Noto Regular: '.$d['regular_path']);
        $this->line('  readable: '.($d['regular_readable'] ? 'yes' : 'no'));
        $this->line('Noto Bold: '.$d['bold_path']);
        $this->line('  readable: '.($d['bold_readable'] ? 'yes' : 'no'));
        $this->newLine();
        if ($d['registration_ok'] === null) {
            $this->line('registerFont: (skipped — prerequisites failed)');
        } else {
            $this->line('registerFont: '.($d['registration_ok'] ? 'ok' : 'failed'));
        }

        foreach ($d['messages'] as $msg) {
            $this->warn($msg);
        }

        if ($d['ok']) {
            $this->info('DOMPDF_ARABIC_FONT_OK: Arabic PDF font pipeline is ready.');

            return 0;
        }

        $this->error('DOMPDF_ARABIC_FONT_NOT_OK: Fix the issues above before relying on Arabic PDFs in production.');
        $this->line('Hints: '.DompdfArabicPdfSupport::REMEDIATION_STORAGE);
        $this->line('        '.DompdfArabicPdfSupport::REMEDIATION_FONT_FILES);

        return $this->option('fail') ? 1 : 0;
    }
}
