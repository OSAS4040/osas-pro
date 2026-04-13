<?php

/**
 * WAVE 1 / PR5 — auth rate limits, failed-attempt windows, and telemetry copy keys.
 * Login per-minute: use AUTH_SECURITY_LOGIN_PER_MINUTE or fall back to saas.login_rate_limit_per_minute in AppServiceProvider.
 */
return [
    'cache_prefix' => 'auth_sec_v1',

    'login' => [
        'per_minute' => env('AUTH_SECURITY_LOGIN_PER_MINUTE'),
    ],

    'otp_verify' => [
        'per_minute' => max(5, min(120, (int) env('AUTH_SECURITY_OTP_VERIFY_PER_MINUTE', 30))),
    ],

    'otp_resend' => [
        'per_minute' => max(3, min(60, (int) env('AUTH_SECURITY_OTP_RESEND_PER_MINUTE', 6))),
    ],

    'failed_password_login' => [
        'window_seconds'       => max(60, min(86400, (int) env('AUTH_SECURITY_FAILED_LOGIN_WINDOW', 900))),
        'burst_signal_after_attempts' => max(5, min(200, (int) env('AUTH_SECURITY_FAILED_LOGIN_BURST_AT', 25))),
    ],

    'failed_otp_verify' => [
        'window_seconds'       => max(60, min(86400, (int) env('AUTH_SECURITY_FAILED_OTP_WINDOW', 900))),
        'burst_signal_after_attempts' => max(3, min(100, (int) env('AUTH_SECURITY_FAILED_OTP_BURST_AT', 12))),
    ],

    'rate_limit_signal_debounce_seconds' => max(10, min(600, (int) env('AUTH_SECURITY_RL_SIGNAL_DEBOUNCE', 60))),

    /**
     * فقط APP_ENV=local و APP_DEBUG=true: تجاوز التحقق من كلمة المرور لبريد مُدرج في dev_passwordless_emails.
     * لا تُفعّل أبداً على خادم مشترك أو staging أو إنتاج.
     */
    'dev_passwordless' => filter_var(env('AUTH_DEV_PASSWORDLESS', false), FILTER_VALIDATE_BOOLEAN),

    /** @var list<string> */
    'dev_passwordless_emails' => array_values(array_filter(array_map(
        static fn (string $e): string => strtolower(trim($e)),
        explode(',', (string) env('AUTH_DEV_PASSWORDLESS_EMAILS', '')),
    ))),

    'messages' => [
        'ar' => [
            'auth.security.rate_limited' => 'محاولات كثيرة. حاول لاحقاً.',
            'auth.phone.invalid_otp'     => 'رمز غير صالح أو منتهٍ.',
            'auth.phone.otp_expired'     => 'انتهت صلاحية الرمز.',
            'auth.phone.otp_not_found'   => 'لا يوجد رمز تحقق لهذا الرقم.',
            'auth.phone.otp_locked'      => 'تم قفل التحقق مؤقتاً لهذا الرقم.',
            'auth.phone.otp_max_attempts'=> 'تجاوزت الحد المسموح من المحاولات.',
            'auth.phone.invalid_phone'   => 'رقم الجوال غير صالح.',
        ],
        'en' => [
            'auth.security.rate_limited' => 'Too many attempts. Please try again later.',
            'auth.phone.invalid_otp'     => 'Invalid or expired verification code.',
            'auth.phone.otp_expired'     => 'The verification code has expired.',
            'auth.phone.otp_not_found'   => 'No verification code found for this number.',
            'auth.phone.otp_locked'      => 'Verification is temporarily locked for this number.',
            'auth.phone.otp_max_attempts'=> 'Too many incorrect attempts.',
            'auth.phone.invalid_phone'   => 'Invalid phone number.',
        ],
    ],
];
