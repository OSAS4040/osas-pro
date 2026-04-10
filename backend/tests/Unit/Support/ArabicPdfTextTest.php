<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ArabicPdfText;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ArabicPdfTextTest extends TestCase
{
    #[Test]
    public function as_dompdf_html_wraps_empty_as_empty(): void
    {
        $this->assertSame('', ArabicPdfText::asDompdfHtml(null)->toHtml());
        $this->assertSame('', ArabicPdfText::asDompdfHtml('')->toHtml());
    }

    #[Test]
    public function as_dompdf_html_wraps_arabic_in_rtl_embed_span(): void
    {
        $html = ArabicPdfText::asDompdfHtml('أمر عمل')->toHtml();

        $this->assertStringContainsString('class="dompdf-ar-shape"', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ar"', $html);
        $this->assertStringContainsString('unicode-bidi:embed', $html);
    }

    #[Test]
    public function as_dompdf_html_wraps_ascii_in_ltr_isolate_span(): void
    {
        $html = ArabicPdfText::asDompdfHtml('WO-1')->toHtml();

        $this->assertStringContainsString('class="dompdf-ar-ascii"', $html);
        $this->assertStringContainsString('dir="ltr"', $html);
    }

    #[Test]
    public function shape_applies_utf8_glyphs_to_arabic(): void
    {
        $raw = 'أمر عمل';
        $shaped = ArabicPdfText::shape($raw);

        $this->assertNotSame($raw, $shaped);
    }

    #[Test]
    public function shape_leaves_ascii_unchanged(): void
    {
        $this->assertSame('WO-1', ArabicPdfText::shape('WO-1'));
    }
}
