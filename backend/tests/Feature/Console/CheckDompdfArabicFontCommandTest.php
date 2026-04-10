<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Tests\TestCase;

final class CheckDompdfArabicFontCommandTest extends TestCase
{
    public function test_dompdf_check_arabic_font_command_runs(): void
    {
        $this->artisan('dompdf:check-arabic-font')
            ->assertSuccessful();
    }
}
