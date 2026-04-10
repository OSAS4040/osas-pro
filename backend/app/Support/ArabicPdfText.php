<?php

declare(strict_types=1);

namespace App\Support;

use ArPHP\I18N\Arabic;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * تجهيز النص العربي لـ Dompdf 3 + Noto Naskh Arabic.
 *
 * سابقاً: utf8Glyphs من ar-php + span بـ dir=ltr — على Dompdf 3 يؤدي غالباً إلى **انعكاس بصري**
 * للنص داخل مستند RTL (يظهر عنوان مثل «بيانات الجهة…» مقلوباً حرفياً).
 *
 * المعتمد الآن: **النص المنطقي UTF-8 كما هو** داخل span بـ `dir="rtl"` و`unicode-bidi: embed`
 * و`display: inline-block` حتى يحترم محرك التخطيط اتجاه العربية دون قلب إضافي.
 * السلاسل بلا حروف عربية تُلف بـ `dir="ltr"` + isolate حتى لا تنعكس الأرقام/المراجع داخل فقرة RTL.
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

        if (! preg_match('/\p{Arabic}/u', $text)) {
            return new HtmlString(
                '<span class="dompdf-ar-ascii" dir="ltr" style="unicode-bidi:isolate;display:inline-block;">'.e($text).'</span>'
            );
        }

        return new HtmlString(
            '<span class="dompdf-ar-shape" dir="rtl" lang="ar" style="unicode-bidi:embed;display:inline-block;text-align:right;">'.e($text).'</span>'
        );
    }

    /**
     * إعادة ترتيب الحروف للعرض على محركات قديمة لا تدعم ربط العربية (اختياري / اختبارات).
     */
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
