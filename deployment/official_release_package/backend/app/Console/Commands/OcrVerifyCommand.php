<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ocr\TesseractOcrRunner;
use Illuminate\Console\Command;

class OcrVerifyCommand extends Command
{
    protected $signature = 'ocr:verify {--fail : Exit with code 1 if engine or required languages are unavailable}';

    protected $description = 'Verify Tesseract OCR installation (binary, version, language packs)';

    public function handle(TesseractOcrRunner $runner): int
    {
        $d = $runner->diagnose();

        if (! $d['available']) {
            $this->error($d['error'] ?? 'Tesseract not available.');

            return $this->option('fail') ? 1 : 0;
        }

        $this->info('Binary: '.$d['binary']);
        $this->info('Version: '.($d['version'] ?? '(unknown)'));
        $this->line('Languages: '.(count($d['langs']) ? implode(', ', $d['langs']) : '(none reported)'));

        if ($d['missing_langs'] !== []) {
            $this->warn('Required packs missing: '.implode(', ', $d['missing_langs']));
            $this->warn($d['error'] ?? '');

            return $this->option('fail') ? 1 : 0;
        }

        if ($d['error']) {
            $this->warn($d['error']);
        }

        $this->info('OCR_READY: Tesseract is usable with required languages.');

        return 0;
    }
}
