import { test, expect } from '@playwright/test'
import { resolveCustomerPortalCredentials } from './helpers/customerPortalAuth'

/**
 * إنشاء مستخدم فريق من بوابة العميل ثم حذفه (يتطلب نفس بيئة customer-portal-routes).
 */
test.describe.configure({ mode: 'serial' })

test.describe('بوابة العميل — حسابات فريق العمل', () => {
  test('إضافة مستخدم ثم حذفه مع تأكيد الحذف', async ({ page, request, baseURL }) => {
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
        `تعذّر تسجيل دخول العميل (HTTP ${loginRes.status()}). ${errText.slice(0, 200)}`,
      )
    }

    const json = (await loginRes.json()) as { token?: string }
    if (!json.token) {
      test.skip(true, 'استجابة الدخول بدون token')
    }

    await page.goto(`${baseURL}/customer/login`)
    await page.evaluate((t) => localStorage.setItem('auth_token', t), json.token)

    await page.goto(`${baseURL}/customer/team-users`, { waitUntil: 'domcontentloaded' })
    await expect(page.getByRole('heading', { name: 'حسابات فريق العمل' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'إضافة موظف' })).toBeEnabled({ timeout: 90_000 })

    const uniqueEmail = `e2e-team-${Date.now()}@test.local`

    await page.getByRole('button', { name: 'إضافة موظف' }).click()
    const formModal = page.locator('div.max-w-lg.rounded-xl').filter({ hasText: 'إضافة موظف جديد' })
    await expect(formModal).toBeVisible()

    await formModal.locator('input[type="text"]').first().fill('مستخدم E2E')
    await formModal.locator('input[type="email"]').fill(uniqueEmail)
    await formModal.locator('input[type="password"]').fill('Password123!')
    await formModal.locator('label:has-text("إرسال رسالة") input[type="checkbox"]').uncheck()

    await formModal.getByRole('button', { name: 'حفظ' }).click()
    await expect(formModal).toBeHidden({ timeout: 60_000 })

    const row = page.getByRole('row').filter({ hasText: uniqueEmail })
    await expect(row).toBeVisible({ timeout: 30_000 })

    await row.getByRole('button', { name: 'حذف' }).click()
    await page.getByRole('dialog').getByRole('button', { name: 'حذف' }).click()
    await expect(row).toHaveCount(0)
  })
})
