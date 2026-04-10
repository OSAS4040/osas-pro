<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Company;
use App\Support\BrandDisplayNames;
use PHPUnit\Framework\TestCase;

final class BrandDisplayNamesTest extends TestCase
{
    public function test_legacy_maps_to_asas_pro(): void
    {
        $c = new Company(['name' => 'OSAS Platform', 'name_ar' => 'منصة أواس']);
        $this->assertSame('أسس برو', BrandDisplayNames::companyTradeNameAr($c));
        $this->assertSame('Osas Pro', BrandDisplayNames::companyTradeNameEn($c));
    }

    public function test_custom_company_unchanged(): void
    {
        $c = new Company(['name' => 'My Workshop', 'name_ar' => 'ورشتي']);
        $this->assertSame('ورشتي', BrandDisplayNames::companyTradeNameAr($c));
        $this->assertSame('My Workshop', BrandDisplayNames::companyTradeNameEn($c));
    }
}
