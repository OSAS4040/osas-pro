/**
 * تحقق واجهة «إدارة المنصّة بالنيابة عن مزوّد خدمة»: مركز التنفيذ → تثبيت شركة → إنشاء خدمة مع ترويسة التفويض.
 *
 * متطلبات: API + Vite (Docker :5173 أو dev). بذرة شركات شريك تنفيذ (مثلاً DemoExecutionPartnerCompanySeeder).
 * تشغيل ضد خادم قائم:
 *   PLAYWRIGHT_NO_WEB_SERVER=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:5173 npx playwright test e2e/platform-on-behalf-catalog-smoke.spec.ts
 */
import { test, expect } from '@playwright/test'
import { loginAsPlatformOperator } from './helpers/platformAdminLogin'

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test.describe('منصّة بالنيابة — مركز التنفيذ والكتالوج', () => {
  test('تحميل /execution-hub دون إعادة قسرية إلى /platform/overview', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'PLAYWRIGHT_BASE_URL / baseURL مطلوب عند التشغيل بدون webServer')

    await loginAsPlatformOperator(page)
    await page.goto('/execution-hub', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/platform\/overview/, { timeout: 15_000 })
    await expect(page.locator('h1.page-title-xl').first()).toBeVisible({ timeout: 20_000 })
  })

  test('إنشاء خدمة يرسل X-On-Behalf-Company-Id بعد اختيار شريك التنفيذ', async ({ page, baseURL }) => {
    test.skip(!baseURL, 'PLAYWRIGHT_BASE_URL / baseURL مطلوب')

    const behalves: string[] = []
    page.on('request', (req) => {
      const u = req.url()
      if (req.method() !== 'POST') return
      if (!u.includes('/api/v1/services')) return
      if (u.match(/\/api\/v1\/services\/\d+/)) return
      const v = req.headers()['x-on-behalf-company-id']
      if (v) behalves.push(v)
    })

    await loginAsPlatformOperator(page)
    await page.goto('/execution-hub', { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/platform\/overview/, { timeout: 15_000 })

    await page.waitForResponse(
      (r) => r.url().includes('/api/v1/platform/companies') && r.request().method() === 'GET' && r.ok(),
      { timeout: 45_000 },
    ).catch(() => {})

    const sel = page.locator('select.field').first()
    await expect(sel).toBeVisible({ timeout: 20_000 })
    const optCount = await sel.locator('option').count()
    test.skip(optCount <= 1, 'لا توجد شركات شريك تنفيذ — أضف بذرة DemoExecutionPartnerCompanySeeder أو ما يعادلها')

    const second = sel.locator('option').nth(1)
    const companyId = await second.getAttribute('value')
    expect(companyId).toBeTruthy()
    await sel.selectOption(companyId!)

    await page.goto('/services-products/services', { waitUntil: 'domcontentloaded' })
    await page.getByRole('button', { name: /إضافة خدمة|Add Service/i }).click()

    const modal = page
      .locator('div.fixed.inset-0')
      .filter({ has: page.getByRole('heading', { name: /إنشاء خدمة|Create service/i }) })
    await expect(modal).toBeVisible({ timeout: 15_000 })

    const name = `e2e-onbehalf-${Date.now()}`
    await modal.locator('input[type="text"]').first().fill(name)
    await modal.locator('input[type="number"]').first().fill('199')

    await modal.getByRole('button', { name: /إنشاء|Create/i }).click()

    await expect
      .poll(() => behalves.length, { timeout: 30_000 })
      .toBeGreaterThan(0)

    expect(behalves[behalves.length - 1]).toBe(String(companyId))
  })
})
