<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\PDF as PdfWrapper;
use Illuminate\Support\Facades\File;

/**
 * فحص جاهزية خط Noto Naskh Arabic لـ Dompdf (ملفات، صلاحيات، تسجيل فعلي).
 * يُستخدم من أمر Artisan وللمراقبة دون تكرار المنطق.
 */
final class DompdfArabicFontDiagnostics
{
    /**
     * @return array{
     *   ok: bool,
     *   storage_fonts_dir: string,
     *   storage_fonts_exists: bool,
     *   storage_fonts_writable: bool,
     *   regular_path: string,
     *   regular_readable: bool,
     *   bold_path: string,
     *   bold_readable: bool,
     *   registration_ok: bool|null,
     *   messages: list<string>
     * }
     */
    public static function run(?PdfWrapper $pdf = null): array
    {
        $fontDir = storage_path('fonts');
        $regular = resource_path('fonts/NotoNaskhArabic-Regular.ttf');
        $bold = resource_path('fonts/NotoNaskhArabic-Bold.ttf');

        $messages = [];
        $storageExists = File::exists($fontDir);
        if (! $storageExists) {
            try {
                File::makeDirectory($fontDir, 0775, true);
                $storageExists = File::exists($fontDir);
            } catch (\Throwable) {
                // reported via writable check below
            }
        }
        $storageWritable = is_dir($fontDir) && is_writable($fontDir);
        $regularOk = is_readable($regular);
        $boldOk = is_readable($bold);

        if (! $storageExists) {
            $messages[] = 'storage/fonts does not exist (will be created on first PDF if permissions allow).';
        } elseif (! $storageWritable) {
            $messages[] = 'storage/fonts is not writable: '.DompdfArabicPdfSupport::REMEDIATION_STORAGE;
        }

        if (! $regularOk) {
            $messages[] = 'Missing or unreadable: '.$regular.' — '.DompdfArabicPdfSupport::REMEDIATION_FONT_FILES;
        }

        $registrationOk = null;
        if ($pdf !== null && $storageWritable && $regularOk) {
            $registrationOk = DompdfArabicFont::registerNotoNaskhArabic($pdf, 'DompdfArabicFontDiagnostics', logOnFailure: false);
            if (! $registrationOk) {
                $messages[] = 'Dompdf registerFont() failed for Noto Naskh Arabic. '.DompdfArabicPdfSupport::REMEDIATION_REGISTER;
            }
        }

        $ok = $storageWritable && $regularOk;
        if ($pdf !== null) {
            $ok = $ok && ($registrationOk === true);
        }

        return [
            'ok' => $ok,
            'storage_fonts_dir' => $fontDir,
            'storage_fonts_exists' => $storageExists,
            'storage_fonts_writable' => $storageWritable,
            'regular_path' => $regular,
            'regular_readable' => $regularOk,
            'bold_path' => $bold,
            'bold_readable' => $boldOk,
            'registration_ok' => $registrationOk,
            'messages' => $messages,
        ];
    }
}
