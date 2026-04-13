<?php

declare(strict_types=1);

namespace App\Support;

/**
 * ثوابت تشغيلية مشتركة لـ Dompdf + العربية (سجلات، فحوصات، رسائل إصلاح).
 */
final class DompdfArabicPdfSupport
{
    public const REMEDIATION_STORAGE = 'Ensure storage/fonts exists and is writable by the PHP process (e.g. www-data). Dompdf writes font metrics cache there.';

    public const REMEDIATION_FONT_FILES = 'Add NotoNaskhArabic-Regular.ttf and optionally Bold under resources/fonts/ (bundled in this repository).';

    public const REMEDIATION_REGISTER = 'If files exist and storage is writable but registration fails, check Dompdf / barryvdh/laravel-dompdf versions and logs for font parse errors.';

    public const LOG_EVENT_SHAPE = 'dompdf_arabic_text_shape';
}
