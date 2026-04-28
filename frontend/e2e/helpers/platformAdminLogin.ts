import type { Page } from '@playwright/test'

const PLATFORM_EMAIL = process.env.PW_PLATFORM_EMAIL ?? 'platform-demo@osas.sa'
const PLATFORM_PASSWORD = process.env.PW_PLATFORM_PASSWORD ?? '12345678'

/**
 * دخول مشغّل المنصة عبر /platform/login ثم انتظار ظهور قشرة المنصة في DOM.
 *
 * - لا تعتمد على `/\/platform\//` فقط — يطابق `/platform/login` أيضاً.
 * - يتطلب واجهة تتحدث مع API حقيقي (بروكسي Vite → Laravel) وحساباً من DemoPlatformAdminSeeder أو ما يعادله.
 * - عند التشغيل ضد `vite preview` بدون خادم API سيفشل الدخول — استخدم `PLAYWRIGHT_BASE_URL` لبيئة متكاملة أو شغّل الـ backend محلياً.
 */
export async function loginAsPlatformOperator(page: Page): Promise<void> {
  await page.goto('/platform/login')
  await page.locator('input[type="email"]').first().fill(PLATFORM_EMAIL)
  await page.locator('input[type="password"]').first().fill(PLATFORM_PASSWORD)
  await page.getByRole('button', { name: /دخول لوحة المنصة/i }).click()
  // بعض الحسابات قد تهبط أولاً على بوابة فريق العمل؛ نثبت الدخول على سياق المنصة صراحة.
  await page.waitForLoadState('networkidle')
  await page.goto('/platform/overview')
  await page.getByTestId('platform-admin-root').waitFor({ state: 'visible', timeout: 90_000 })
}
