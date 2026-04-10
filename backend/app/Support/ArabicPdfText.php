<?php

declare(strict_types=1);

namespace App\Support;

use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * تشكيل النص العربي لـ Dompdf.
 *
 * Dompdf (محرك CPDF) لا يطبّق ربط الحروف ولا اتجاه ثنائي الاتجاه كالمتصفحات؛ إرجاع النص
 * الخام مع خط Noto يُظهر الحروف منفصلة ومرتبة بصريًا بشكل خاطئ. الحل المعتمد: دائمًا
 * utf8Glyphs من ar-php، وعرض النتيجة داخل span باتجاه ltr وunicode-bidi:isolate حتى لا
 * يعيد مستند dir="rtl" قلب الترتيب البصري لسلسلة «الترتيب البصري».
 */
final class ArabicPdfText
{
    /**
     * نص جاهز لدمجه في قوالب PDF عبر {{ $shape(...) }} — يُعاد HtmlString فلا يُهرب الوسم.
     */
    public static function asDompdfHtml(?string $text): HtmlString
    {
        if ($text === null || $text === '') {
            return new HtmlString('');
        }

        $shaped = self::shape($text);

        return new HtmlString(
            '<span class="dompdf-ar-shape" dir="ltr" style="unicode-bidi:isolate">'.e($shaped).'</span>'
        );
    }

    public static function shape(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if (! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        try {
            return (new Arabic())->utf8Glyphs($text);
        } catch (\Throwable $e) {
            Log::warning(
                '[dompdf:arabic-text] utf8Glyphs failed — Arabic PDF line may render incorrectly.',
                [
                    'event' => DompdfArabicPdfSupport::LOG_EVENT_SHAPE,
                    'reason' => 'utf8_glyphs_exception',
                    'error' => $e->getMessage(),
                ]
            );

            return $text;
        }
    }
}
