<?php

/**
 * إعدادات تشغيل منصة SaaS (أسس برو).
 *
 * SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT — عند true يسمح لأي مستأجر يملك صلاحية subscriptions.manage
 * بتعديل سجلات جدول plans العالمية (مناسب لتثبيت ذاتي/تجربة؛ غير مُنصح لإنتاج متعدد المستأجرين).
 *
 * SAAS_PLATFORM_ADMIN_EMAILS — قائمة بريدية مفصولة بفواصل لمشغّلي المنصة (قراءة المشتركين + تعديل كتالوج الباقات عندما يكون التعديل من المستأجرين مغلقاً).
 */
return [
    'allow_tenant_plan_catalog_edit' => filter_var(
        env('SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT', false),
        FILTER_VALIDATE_BOOL
    ),

    'platform_admin_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        explode(',', (string) env('SAAS_PLATFORM_ADMIN_EMAILS', ''))
    ))),

    /** خطوة تحقق إضافية عند تسجيل الدخول (رمز يُرسل بالبريد). */
    'login_otp_enabled' => filter_var(env('SAAS_LOGIN_OTP_ENABLED', false), FILTER_VALIDATE_BOOL),

    'login_otp_ttl_seconds' => max(60, min(900, (int) env('SAAS_LOGIN_OTP_TTL', 300))),

    'password_reset_ttl_seconds' => max(300, min(86400, (int) env('SAAS_PASSWORD_RESET_TTL', 3600))),
];
