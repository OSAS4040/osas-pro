/**
 * Phase 2 closeout — UI smoke for /admin (read-only verification).
 * Credentials match DemoPlatformAdminSeeder / i18n dev hints (public in locale files).
 * Set PLAYWRIGHT_BASE_URL (e.g. http://127.0.0.1) and PLAYWRIGHT_NO_WEB_SERVER=1 when a dev server is already running.
 *
 * يتطلب خادماً يستجيب لـ API (بروكسي) وحساب مشغّل منصة صالح — نفس بيئة Phase 4.
 */
import { test, expect } from '@playwright/test'
import { loginAsPlatformOperator } from './helpers/platformAdminLogin'

const TENANT_EMAIL = process.env.PW_TENANT_EMAIL ?? 'owner@demo.sa'
const TENANT_PASSWORD = process.env.PW_TENANT_PASSWORD ?? 'password'

test.describe('Phase 2 — platform admin UI smoke', () => {
  /** الدخول + تحميل لوحة المنصة قد يتجاوز 60s مع خلفية Laravel بطيئة أو شبكة CI */
  test.describe.configure({ timeout: 120_000 })

  test('platform operator: /admin shows executive dashboard; survives refresh', async ({ page }) => {
    await loginAsPlatformOperator(page)

    await page.goto('/admin')
    await expect(page).toHaveURL(/\/platform\/overview/, { timeout: 45_000 })
    await expect(page.getByTestId('platform-executive-dashboard-title')).toBeVisible({ timeout: 45_000 })
    await expect(page.getByText(/مركز قيادة تنفيذي|قراءة فقط/i).first()).toBeVisible()

    await expect(page.getByRole('heading', { name: /الصحة التشغيلية/ }).first()).toBeVisible({ timeout: 30_000 })
    await expect(
      page.getByText(/مؤشرات المنصة الرئيسية|تنبيهات تشغيلية|المحرك الذكي|ذكاء النشاط/i).first(),
    ).toBeVisible({ timeout: 30_000 })

    await page.reload()
    await expect(page).toHaveURL(/\/platform\/overview/, { timeout: 45_000 })
    await expect(page.getByTestId('platform-executive-dashboard-title')).toBeVisible({ timeout: 45_000 })
  })

  test('tenant staff: /admin redirects away (no platform access)', async ({ page }) => {
    await page.goto('/login')
    await page.locator('input[type="email"], input[autocomplete="username"]').first().fill(TENANT_EMAIL)
    await page.locator('input[type="password"]').first().fill(TENANT_PASSWORD)
    await page.getByRole('button', { name: /دخول|Sign in|Login/i }).click()
    await page.waitForLoadState('networkidle')

    await page.goto('/')
    await page.goto('/admin')
    await expect(page).not.toHaveURL(/\/admin\/?$/)
  })

  test('platform overview: in-page nav scrolls to pulse; button exposes aria-controls', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/overview')
    await expect(page.getByTestId('platform-executive-dashboard-title')).toBeVisible({ timeout: 45_000 })

    const inPage = page.getByTestId('platform-admin-in-page-nav')
    await expect(inPage).toBeVisible({ timeout: 20_000 })

    await expect(page.locator('#platform-in-page-nav-platform-overview-pulse')).toHaveAttribute(
      'aria-controls',
      'platform-overview-pulse',
    )

    await inPage.getByRole('button', { name: /نبض المنصة/ }).click()

    await expect(page.locator('#platform-overview-pulse')).toBeVisible({ timeout: 10_000 })
  })

  test('company detail: in-page nav scrolls within overview tab', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/companies')
    await expect(page.getByTestId('platform-admin-root')).toBeVisible({ timeout: 45_000 })

    const openCount = await page.getByRole('button', { name: /فتح ملف الشركة/ }).count()
    test.skip(openCount === 0, 'لا توجد شركات في الجدول — تخطّي اختبار تفاصيل الشركة')

    await page.getByRole('button', { name: /فتح ملف الشركة/ }).first().click()
    await expect(page).toHaveURL(/\/platform\/companies\/\d+/, { timeout: 20_000 })

    const detailNav = page.getByTestId('platform-admin-in-page-nav')
    await expect(detailNav).toBeVisible({ timeout: 20_000 })

    await detailNav.getByRole('button', { name: /التنبيهات/ }).click()

    await expect
      .poll(
        async () =>
          page.evaluate(() => {
            const el = document.getElementById('platform-company-overview-alerts')
            if (!el) return false
            const r = el.getBoundingClientRect()
            return r.top >= -48 && r.top < window.innerHeight * 0.48
          }),
        { timeout: 10_000 },
      )
      .toBe(true)
  })
})
