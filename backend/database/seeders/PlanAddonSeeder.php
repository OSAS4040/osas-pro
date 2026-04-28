<?php

namespace Database\Seeders;

use App\Models\PlanAddon;
use Illuminate\Database\Seeder;

/**
 * إضافات مدفوعة على الباقة الأساسية — كتالوج المنصة (يمكن للعميل شراء ميزة دون ترقية الباقة بالكامل).
 */
class PlanAddonSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'slug'                => 'addon_smart_reports',
                'feature_key'         => 'smart_reports',
                'name'                => 'Smart reports & signals',
                'name_ar'             => 'التقارير الذكية والتنبيهات',
                'description_ar'      => 'تقارير تحليلية متقدمة، مؤشرات تشغيل، وتنبيهات ذكية فوق تقارير الباقة الأساسية.',
                'price_monthly'       => 79,
                'price_yearly'        => 790,
                'currency'            => 'SAR',
                'eligible_plan_slugs' => ['trial', 'basic', 'professional', 'enterprise'],
                'is_active'           => true,
                'sort_order'          => 1,
            ],
            [
                'slug'                => 'addon_advanced_wo_pricing',
                'feature_key'         => 'work_order_advanced_pricing',
                'name'                => 'Advanced work order pricing',
                'name_ar'             => 'تسعير أوامر عمل متقدم',
                'description_ar'      => 'سياسات تسعير متعددة المستويات وخصومات مرتبطة بالأوامر — مفيد عندما لا تتضمن الباقة هذا التمكين.',
                'price_monthly'       => 59,
                'price_yearly'        => 590,
                'currency'            => 'SAR',
                'eligible_plan_slugs' => ['trial', 'basic'],
                'is_active'           => true,
                'sort_order'          => 2,
            ],
            [
                'slug'                => 'addon_zatca_addon',
                'feature_key'         => 'zatca',
                'name'                => 'ZATCA Phase 2 enablement',
                'name_ar'             => 'تمكين ZATCA المرحلة 2',
                'description_ar'      => 'ربط الفوترة الإلكترونية والامتثال المرحلي 2 كإضافة على باقات لا تشمله افتراضياً.',
                'price_monthly'       => 129,
                'price_yearly'        => 1290,
                'currency'            => 'SAR',
                'eligible_plan_slugs' => ['trial', 'basic'],
                'is_active'           => true,
                'sort_order'          => 3,
            ],
        ];

        foreach ($rows as $row) {
            PlanAddon::updateOrCreate(
                ['slug' => $row['slug']],
                $row,
            );
        }
    }
}
