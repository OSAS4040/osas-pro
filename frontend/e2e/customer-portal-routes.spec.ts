import { test, expect } from '@playwright/test'
import { CUSTOMER_PORTAL_NAV_PATHS } from '../src/config/customerPortalPaths'
import { resolveCustomerPortalCredentials } from './helpers/customerPortalAuth'

/**
 * يمرّ على كل مسار من قائمة بوابة العميل بجلسة عميل تجريبية.
 *
 * متطلبات:
 * - الواجهة + API (مثلاً `vite preview` مع بروكسي إلى Laravel، أو Docker على :80).
 * - بذرة العرض: `php artisan db:seed --class=Database\\Seeders\\DemoCompanySeeder`
 *
 * متغيرات اختيارية: `PW_CUSTOMER_PORTAL_EMAIL`، `PW_CUSTOMER_PORTAL_PASSWORD`
 *
 * تشغيل مع خادم يدوي:
 *   PLAYWRIGHT_NO_WEB_SERVER=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:5173 npx playwright test e2e/customer-portal-routes.spec.ts
 */
test.describe.configure({ mode: 'serial' })

test.describe('بوابة العميل — مسارات القائمة', () => {
  test('كل رابط يحمّل دون 404 وتبقى الجلسة ضمن البوابة أو صفحة رفض واضحة', async ({
    page,
    request,
    baseURL,
  }) => {
    test.setTimeout(180_000)
    test.skip(!baseURL, 'PLAYWRIGHT_BASE_URL / baseURL مطلوب')

    const { email, password } = resolveCustomerPortalCredentials()
    const loginRes = await request.post(`${baseURL}/api/v1/auth/login`, {
      data: { email, password },
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })

    if (!loginRes.ok()) {
      const errText = await loginRes.text().catch(() => '')
      test.skip(
        true,
        `تعذّر تسجيل دخول العميل (HTTP ${loginRes.status()}). شغّل الـ API والبذرة DemoCompanySeeder. ${errText.slice(0, 200)}`,
      )
    }

    const json = (await loginRes.json()) as { token?: string }
    if (!json.token) {
      test.skip(true, 'استجابة الدخول بدون token — تحقق من /api/v1/auth/login')
    }

    await page.goto(`${baseURL}/customer/login`)
    await page.evaluate((t) => localStorage.setItem('auth_token', t), json.token)

    await page.goto(`${baseURL}/customer/dashboard`, { waitUntil: 'domcontentloaded' })
    await expect(page).toHaveURL(/\/customer\/dashboard/)

    for (const path of CUSTOMER_PORTAL_NAV_PATHS) {
      await test.step(path, async () => {
        await page.goto(`${baseURL}${path}`, { waitUntil: 'domcontentloaded', timeout: 90_000 })

        await expect(page.getByRole('heading', { level: 1, name: '404' })).toHaveCount(0)

        const url = page.url()
        expect(url, `${path}: لم يُفترض إعادة إلى صفحة الدخول`).not.toMatch(
          /\/customer\/login(?:\?|$)/,
        )

        const okDestination =
          url.includes('/customer/')
          || url.includes('/access-denied')
        expect(okDestination, `${path}: توجيه غير متوقع → ${url}`).toBe(true)
      })
    }
  })
})
