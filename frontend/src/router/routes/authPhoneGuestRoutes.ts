import type { RouteRecordRaw } from 'vue-router'

export const authPhoneGuestRoutes: RouteRecordRaw[] = [
  /** توافق مع توجيه «غير مشغّل المنصة» بعيداً عن `/admin` */
  { path: '/dashboard', redirect: '/' },
  // ── Auth — دخول مزوّد الخدمة (/login)؛ بوابات أخرى في مسارات منفصلة ──
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  { path: '/staff/login', redirect: '/login' },
  {
    path: '/platform/login',
    name: 'platform-login',
    component: () => import('@/views/auth/PlatformAdminLoginView.vue'),
    meta: { guest: true, platformAdminLogin: true },
  },
  { path: '/admin/login', redirect: '/platform/login' },
  // تسجيل دخول مخصص لكل بوابة (فصل تدريجي دون كسر المصادقة الحالية)
  /** أسطول بدون صفحة دخول منفصلة — نفس نموذج مزوّد الخدمة (`/login`) */
  { path: '/fleet/login', redirect: '/login' },
  {
    path: '/customer/login',
    name: 'customer-login',
    component: () => import('@/views/auth/CustomerLoginView.vue'),
    meta: { guest: true, portalLogin: 'customer' },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { guest: true },
  },
  {
    path: '/phone',
    name: 'phone-auth',
    component: () => import('@/views/phone/PhoneAuthView.vue'),
    meta: { guest: true },
  },
  {
    path: '/phone/verify',
    name: 'phone-verify',
    component: () => import('@/views/phone/PhoneOtpVerifyView.vue'),
    meta: { guest: true },
  },
  {
    path: '/phone/onboarding',
    name: 'phone-onboarding',
    component: () => import('@/views/phone/PhoneOnboardingHubView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/type',
    name: 'phone-onboarding-type',
    component: () => import('@/views/phone/RegistrationAccountTypeView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/individual',
    name: 'phone-onboarding-individual',
    component: () => import('@/views/phone/RegistrationIndividualView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/company',
    name: 'phone-onboarding-company',
    component: () => import('@/views/phone/RegistrationCompanyView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/pending-review',
    name: 'phone-onboarding-pending',
    component: () => import('@/views/phone/CompanyPendingReviewView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/phone/onboarding/done',
    name: 'phone-onboarding-done',
    component: () => import('@/views/phone/PhoneOnboardingDoneView.vue'),
    meta: { requiresAuth: true },
  },
]
