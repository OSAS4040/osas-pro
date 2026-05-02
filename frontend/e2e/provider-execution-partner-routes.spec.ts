import { test, expect } from '@playwright/test'
import { EXECUTION_PARTNER_STAFF_NAV_PATHS } from '../src/config/executionPartnerStaffPaths'

/**
 * يمرّ على مسارات موظف «شريك تنفيذ المنصة» (ديمو: DemoExecutionPartnerCompanySeeder).
 *
 * متطلبات: API + واجهة (Docker nginx :80 أو vite preview مع بروكسي).
 * بذرة: `php artisan db:seed --class=Database\\Seeders\\DemoExecutionPartnerCompanySeeder`
 *
 * بيانات الدخول الافتراضية: owner.execution@demo.sa / Password123!
 *
 * تشغيل مع خادم يدوي:
 *   PLAYWRIGHT_NO_WEB_SERVER=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:5173 npx playwright test e2e/provider-execution-partner-routes.spec.ts
 */
test.describe.configure({ mode: 'serial' })

const EP_EMAIL = process.env.PW_EXECUTION_PARTNER_EMAIL?.trim() || 'owner.execution@demo.sa'
const EP_PASSWORD = process.env.PW_EXECUTION_PARTNER_PASSWORD?.trim() || 'Password123!'

test.describe('مزوّد الخدمة (شريك تنفيذ المنصة) — مسارات تشغيلية', () => {
  test('كل رابط يحمّل دون 404 للصفحة الكاملة', async ({ page, request, baseURL }) => {
    test.setTimeout(180_000)
    test.skip(!baseURL, 'PLAYWRIGHT_BASE_URL / baseURL مطلوب')

    const loginRes = await request.post(`${baseURL}/api/v1/auth/login`, {
      data: { email: EP_EMAIL, password: EP_PASSWORD },
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })

    if (!loginRes.ok()) {
      const errText = await loginRes.text().catch(() => '')
      test.skip(
        true,
        `تعذّر تسجيل دخول شريك التنفيذ (HTTP ${loginRes.status()}). شغّل الـ API وبذرة DemoExecutionPartnerCompanySeeder. ${errText.slice(0, 200)}`,
      )
    }

    const json = (await loginRes.json()) as { token?: string }
    if (!json.token) {
      test.skip(true, 'استجابة الدخول بدون token — تحقق من /api/v1/auth/login')
    }

    await page.goto(`${baseURL}/login`)
    await page.evaluate((t) => localStorage.setItem('auth_token', t), json.token)

    await page.goto(`${baseURL}/`, { waitUntil: 'domcontentloaded' })
    await expect(page).not.toHaveURL(/\/login(?:\?|$)/)

    for (const path of EXECUTION_PARTNER_STAFF_NAV_PATHS) {
      await test.step(path, async () => {
        await page.goto(`${baseURL}${path}`, { waitUntil: 'domcontentloaded', timeout: 90_000 })

        await expect(page.getByRole('heading', { level: 1, name: '404' })).toHaveCount(0)

        const url = page.url()
        expect(url, `${path}: لم يُفترض إعادة إلى صفحة الدخول`).not.toMatch(/\/login(?:\?|$)/)
      })
    }
  })
})
