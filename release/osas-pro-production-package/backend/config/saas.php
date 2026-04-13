<?php

/**
 * إعدادات تشغيل منصة SaaS (أسس برو).
 *
 * SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT — عند true يسمح لأي مستأجر يملك صلاحية subscriptions.manage
 * بتعديل سجلات جدول plans العالمية (مناسب لتثبيت ذاتي/تجربة؛ غير مُنصح لإنتاج متعدد المستأجرين).
 *
 * SAAS_PLATFORM_ADMIN_EMAILS — قائمة بريدية مفصولة بفواصل لمشغّلي المنصة (قراءة المشتركين + تعديل كتالوج الباقات عندما يكون التعديل من المستأجرين مغلقاً).
 *
 * SAAS_PLATFORM_ADMIN_PHONES — أرقام جوال مفصولة بفواصل لمشغّلي منصة مستقلين (users.company_id = null فقط).
 * يُطبَّع الرقم بنفس منطق تسجيل الدخول (مثلاً 05xxxxxxxx و 9665xxxxxxxx).
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

    'platform_admin_phones' => array_values(array_filter(array_map(
        static fn (string $p): string => trim($p),
        explode(',', (string) env('SAAS_PLATFORM_ADMIN_PHONES', ''))
    ))),

    /** خطوة تحقق إضافية عند تسجيل الدخول (رمز يُرسل بالبريد). */
    'login_otp_enabled' => filter_var(env('SAAS_LOGIN_OTP_ENABLED', false), FILTER_VALIDATE_BOOL),

    /**
     * قناة إرسال رمز تسجيل الدخول: email | sms | both
     * sms يتطلب TWILIO_* ورقم جوال محفوظ للمستخدم؛ عند الفشل يُحاول البريد إن كان both أو كنسخة احتياطية عند sms فقط.
     */
    'login_otp_channel' => strtolower((string) env('SAAS_LOGIN_OTP_CHANNEL', 'email')),

    'twilio_account_sid' => (string) env('TWILIO_ACCOUNT_SID', ''),
    'twilio_auth_token'  => (string) env('TWILIO_AUTH_TOKEN', ''),
    'twilio_sms_from'    => (string) env('TWILIO_SMS_FROM', ''),

    /** OTP مسار الجوال (تسجيل/دخول بدون كلمة مرور) */
    'phone_otp_ttl_seconds'       => max(60, min(600, (int) env('PHONE_OTP_TTL', 300))),
    'phone_otp_max_attempts'      => max(3, min(12, (int) env('PHONE_OTP_MAX_ATTEMPTS', 8))),
    'phone_otp_send_max_per_phone_window' => max(1, min(20, (int) env('PHONE_OTP_SEND_MAX_PHONE', 5))),
    'phone_otp_send_max_per_ip_window'    => max(5, min(100, (int) env('PHONE_OTP_SEND_MAX_IP', 30))),
    /** عند التعيين في غير الإنتاج: يُسجّل الرمز في السجل (لا يُعاد في JSON للإنتاج) */
    'phone_otp_fake_plaintext'    => (string) env('PHONE_OTP_FAKE_PLAINTEXT', ''),

    'login_otp_ttl_seconds' => max(60, min(900, (int) env('SAAS_LOGIN_OTP_TTL', 300))),

    /** POST /api/v1/auth/login — per IP + identifier composite key */
    'login_rate_limit_per_minute' => max(5, min(120, (int) env('SAAS_LOGIN_RATE_LIMIT_PER_MINUTE', 20))),

    'password_reset_ttl_seconds' => max(300, min(86400, (int) env('SAAS_PASSWORD_RESET_TTL', 3600))),
];
