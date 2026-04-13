<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Runs Tesseract on in-memory image bytes with explicit success/failure codes
 * so API layers can distinguish "binary missing" from "read failed / empty text".
 */
final class TesseractOcrRunner
{
    public const CODE_OK = 'ok';

    public const CODE_DISABLED = 'disabled';

    public const CODE_ENGINE_MISSING = 'engine_missing';

    public const CODE_RUN_FAILED = 'run_failed';

    public const CODE_EMPTY_OUTPUT = 'empty_output';

    public const CODE_IMAGE_TOO_LARGE = 'image_too_large';

    public function binaryPath(): ?string
    {
        if (! (bool) config('ocr.enabled', true)) {
            return null;
        }

        $configured = trim((string) config('ocr.tesseract_path', ''));
        $candidates = array_values(array_unique(array_filter(array_merge(
            $configured !== '' ? [$configured] : [],
            ['/usr/bin/tesseract', '/usr/local/bin/tesseract'],
        ))));

        foreach ($candidates as $p) {
            if (@is_executable($p)) {
                return $p;
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $which = trim((string) shell_exec('where tesseract 2>nul'));
            $first = explode("\r\n", $which)[0] ?: explode("\n", $which)[0] ?: '';
            $first = trim($first);
            if ($first !== '' && @is_executable($first)) {
                return $first;
            }
        } else {
            $which = trim((string) shell_exec('command -v tesseract 2>/dev/null'));
            if ($which !== '' && @is_executable($which)) {
                return $which;
            }
        }

        return null;
    }

    public function isEngineAvailable(): bool
    {
        return $this->binaryPath() !== null;
    }

    /**
     * @return array{code: string, text: ?string, stderr: ?string, exit_code: ?int}
     */
    public function runRaw(string $imgData, string $lang, int $psm): array
    {
        if (! (bool) config('ocr.enabled', true)) {
            Log::notice('OCR: skipped (OCR_ENABLED=false)');

            return ['code' => self::CODE_DISABLED, 'text' => null, 'stderr' => 'ocr_disabled', 'exit_code' => null];
        }

        $maxBytes = (int) config('ocr.max_image_bytes', 12582912);
        if (strlen($imgData) > $maxBytes) {
            Log::notice('OCR: image exceeds max_image_bytes', ['bytes' => strlen($imgData), 'max' => $maxBytes]);

            return ['code' => self::CODE_IMAGE_TOO_LARGE, 'text' => null, 'stderr' => 'image_too_large', 'exit_code' => null];
        }

        $bin = $this->binaryPath();
        if (! $bin) {
            Log::warning('OCR: tesseract binary not found — install tesseract-ocr (+ ara, eng) in the app container or set OCR_TESSERACT_PATH');

            return ['code' => self::CODE_ENGINE_MISSING, 'text' => null, 'stderr' => null, 'exit_code' => null];
        }

        $tmpIn = $this->tempImagePath($imgData);
        $tmpOut = sys_get_temp_dir().'/'.Str::random(16);

        file_put_contents($tmpIn, $imgData);

        try {
            $psm = max(0, min(13, $psm));
            $timeout = (int) config('ocr.timeout_seconds', 90);

            $result = Process::timeout($timeout)->run([
                $bin,
                $tmpIn,
                $tmpOut,
                '-l', $lang,
                '--oem', '3',
                '--psm', (string) $psm,
            ]);

            $exit = $result->exitCode();
            $err = $result->errorOutput();
            $outFile = $tmpOut.'.txt';

            if (! $result->successful()) {
                Log::warning('OCR: tesseract exited with error', [
                    'exit' => $exit,
                    'stderr' => Str::limit(trim($err), 2000),
                ]);
                @unlink($tmpIn);
                @is_file($outFile) && @unlink($outFile);

                return [
                    'code' => self::CODE_RUN_FAILED,
                    'text' => null,
                    'stderr' => trim($err) !== '' ? trim($err) : null,
                    'exit_code' => $exit,
                ];
            }

            if (! is_file($outFile)) {
                Log::notice('OCR: no output .txt from tesseract', ['stderr' => Str::limit(trim($err), 500)]);
                @unlink($tmpIn);

                return [
                    'code' => self::CODE_EMPTY_OUTPUT,
                    'text' => null,
                    'stderr' => trim($err) !== '' ? trim($err) : null,
                    'exit_code' => $exit,
                ];
            }

            $text = trim((string) file_get_contents($outFile));
            @unlink($outFile);
            @unlink($tmpIn);

            if ($text === '') {
                return [
                    'code' => self::CODE_EMPTY_OUTPUT,
                    'text' => null,
                    'stderr' => trim($err) !== '' ? trim($err) : null,
                    'exit_code' => $exit,
                ];
            }

            return ['code' => self::CODE_OK, 'text' => $text, 'stderr' => null, 'exit_code' => $exit];
        } catch (\Throwable $e) {
            @unlink($tmpIn);
            @is_file($tmpOut.'.txt') && @unlink($tmpOut.'.txt');
            Log::error('OCR: exception running tesseract', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return ['code' => self::CODE_RUN_FAILED, 'text' => null, 'stderr' => $e->getMessage(), 'exit_code' => null];
        }
    }

    /**
     * @return array{available: bool, binary: ?string, version: ?string, langs: list<string>, missing_langs: list<string>, error: ?string}
     */
    public function diagnose(): array
    {
        $bin = $this->binaryPath();
        if (! $bin) {
            return [
                'available' => false,
                'binary' => null,
                'version' => null,
                'langs' => [],
                'missing_langs' => [],
                'error' => 'Tesseract binary not found or OCR is disabled (OCR_ENABLED=false).',
            ];
        }

        $v = Process::run([$bin, '--version']);
        $version = $v->successful() ? trim(Str::before($v->output(), "\n")) : null;

        $l = Process::run([$bin, '--list-langs']);
        $langs = [];
        if ($l->successful()) {
            foreach (explode("\n", trim($l->output())) as $line) {
                $line = trim($line);
                if ($line === '' || str_contains(strtolower($line), 'list of available')) {
                    continue;
                }
                $langs[] = $line;
            }
        }

        $required = array_values(array_filter(config('ocr.required_langs', ['ara', 'eng'])));
        $missing = array_values(array_diff($required, $langs));
        $error = $missing !== []
            ? 'Missing language packs: '.implode(', ', $missing).' (install tesseract language data for ara and eng in the container).'
            : null;

        return [
            'available' => true,
            'binary' => $bin,
            'version' => $version,
            'langs' => $langs,
            'missing_langs' => $missing,
            'error' => $error,
        ];
    }

    private function tempImagePath(string $imgData): string
    {
        $base = sys_get_temp_dir().'/'.Str::random(16);
        if (str_starts_with($imgData, "\xFF\xD8\xFF")) {
            return $base.'.jpg';
        }
        if (str_starts_with($imgData, "\x89PNG\r\n\x1a\n")) {
            return $base.'.png';
        }
        if (strlen($imgData) > 12 && str_starts_with($imgData, 'RIFF') && substr($imgData, 8, 4) === 'WEBP') {
            return $base.'.webp';
        }

        return $base.'.jpg';
    }
}
