<?php

/**
 * محتوى قسم الباقات في صفحة الهبوط — يمكن لمشغّل المنصة تعديل الملف وإعادة النشر،
 * أو لاحقاً ربطه بواجهة إدارة. يُعرض عبر GET /api/v1/public/landing-plans
 */
return [
    'plans' => [
        [
            'id'          => 'trial',
            'name'        => 'تجريبي',
            'price_label' => 'مجاناً',
            'period'      => '14 يوماً',
            'highlight'   => false,
            'features'    => [
                'حتى 3 مستخدمين',
                'فرع واحد',
                'أوامر عمل ومخزون أساسي',
            ],
            'cta'         => 'ابدأ التجربة',
            'cta_href'    => '/login',
        ],
        [
            'id'          => 'professional',
            'name'        => 'احترافي',
            'price_label' => 'حسب العقد',
            'period'      => 'سنوياً',
            'highlight'   => true,
            'features'    => [
                'فروع متعددة',
                'تقارير وتكاملات',
                'دعم أولوية',
            ],
            'cta'         => 'تواصل مع المبيعات',
            'cta_href'    => '#contact',
        ],
        [
            'id'          => 'enterprise',
            'name'        => 'مؤسسات',
            'price_label' => 'مخصص',
            'period'      => '',
            'highlight'   => false,
            'features'    => [
                'حوكمة وتدقيق',
                'SLA مخصص',
                'تكاملات على المقاس',
            ],
            'cta'         => 'طلب عرض',
            'cta_href'    => '#contact',
        ],
    ],
    'section_title'       => 'باقات مرنة تناسب نموك',
    'section_subtitle'    => 'ابدأ بالتجربة ثم انتقل للباقة التي تناسب فروعك وفريقك.',
];
