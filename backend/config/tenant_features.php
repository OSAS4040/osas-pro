<?php

use App\Support\TenantBusinessFeatures;

/**
 * تجاوزات تشغيلية لمصفوفة الميزات الفعّالة — دون تعديل JSON في عمود settings للشركة.
 *
 * الاستخدام: البيئة التجريبية، قواعد قديمة، أو مطابقة سريعة مع سياسة المنصة قبل ضبط الواجهة الإدارية.
 *
 * @see TenantBusinessFeatures::effectiveMatrix()
 */
return [
    /**
     * معرفات شركات تُفرَض عليها platform_execution_partner=true (مفصولة بفواصل).
     * مثال: PLATFORM_EXECUTION_PARTNER_COMPANY_IDS=12,44
     */
    'platform_execution_partner_company_ids' => array_values(array_filter(array_map(
        static fn (string $s): int => (int) trim($s),
        explode(',', (string) env('PLATFORM_EXECUTION_PARTNER_COMPANY_IDS', '')),
    ), static fn (int $id): bool => $id > 0)),

    /**
     * عناوين بريد الشركة (كما في companies.email) — مطابقة غير حساسة لحالة الأحرف.
     * مثال: PLATFORM_EXECUTION_PARTNER_COMPANY_EMAILS=execution.partner@demo.sa
     */
    'platform_execution_partner_company_emails' => array_values(array_filter(array_map(
        static fn (string $s): string => strtolower(trim($s)),
        explode(',', (string) env('PLATFORM_EXECUTION_PARTNER_COMPANY_EMAILS', '')),
    ))),
];
