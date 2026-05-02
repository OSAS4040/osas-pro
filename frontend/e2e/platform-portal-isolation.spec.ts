/**
 * تكامل واحد: عزل بوابة إدارة المنصّة عن جلسة الضيف وجلسة موظف المستأجر.
 * يتطلب SPA تعمل مع Vue Router (vite preview أو dev).
 *
 * الجزء الثاني اختياري: يشغّل فقط عند PW_LOGIN_EMAIL و PW_LOGIN_PASSWORD (حساب ورشة، ليس مشغّل منصّة).
 */
import { test, expect } from '@playwright/test'

const STAFF_EMAIL = process.env.PW_LOGIN_EMAIL ?? ''
const STAFF_PASSWORD = process.env.PW_LOGIN_PASSWORD ?? ''

test.describe('عزل بوابة المنصّة', () => {
  test('ضيف → تسجيل المنصّة؛ موظف مستأجر لا يبقى على /platform/*', async ({ page }) => {
    await page.goto('/platform/overview')
    await expect(page).toHaveURL(/\/platform\/login/)

    if (!STAFF_EMAIL || !STAFF_PASSWORD) {
      test.info().annotations.push({
        type: 'skip-reason',
        description:
          'لم يُضبط PW_LOGIN_EMAIL/PW_LOGIN_PASSWORD — يُتحقق من جزء الضيف فقط.',
      })
      return
    }

    await page.goto('/login')
    await page.locator('input[type="email"], input[autocomplete="username"]').first().fill(STAFF_EMAIL)
    await page.locator('input[type="password"]').first().fill(STAFF_PASSWORD)
    await page.getByRole('button', { name: /دخول|Sign in|Login/i }).click()
    await page.waitForLoadState('networkidle')

    await page.goto('/platform/overview')
    await expect(page).not.toHaveURL(/\/platform\/overview/)
    await expect(page.locator('[data-testid="platform-admin-root"]')).toHaveCount(0)
  })
})
