<?php

/**
 * User-facing copy for login eligibility (WAVE 1 / PR1).
 * Keys match LoginEligibilityResult::messageKey values.
 */
return [
    'messages' => [
        'ar' => [
            'auth.login.invalid_credentials' => 'بيانات الدخول غير صحيحة.',
            'auth.login.account_inactive'  => 'هذا الحساب غير مفعّل. تواصل مع المسؤول.',
            'auth.login.account_suspended' => 'هذا الحساب موقوف مؤقتاً. تواصل مع المسؤول.',
            'auth.login.account_blocked'   => 'تم حظر هذا الحساب. تواصل مع الدعم.',
            'auth.login.account_disabled'  => 'تم تعطيل هذا الحساب. تواصل مع المسؤول.',
            'auth.login.not_allowed'       => 'تسجيل الدخول غير مسموح لهذا الحساب.',
            'auth.login.account_not_found' => 'تعذّر إكمال تسجيل الدخول.',
        ],
        'en' => [
            'auth.login.invalid_credentials' => 'The credentials provided are incorrect.',
            'auth.login.account_inactive'  => 'This account is not active. Contact your administrator.',
            'auth.login.account_suspended' => 'This account is suspended. Contact your administrator.',
            'auth.login.account_blocked'   => 'This account has been blocked. Contact support.',
            'auth.login.account_disabled'  => 'This account has been disabled. Contact your administrator.',
            'auth.login.not_allowed'       => 'Sign-in is not allowed for this account.',
            'auth.login.account_not_found' => 'Sign-in could not be completed.',
        ],
    ],
];
