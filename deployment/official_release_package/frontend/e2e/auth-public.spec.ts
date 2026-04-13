import { test, expect } from '@playwright/test'

test.describe('صفحات الضيف (بدون خادم API)', () => {
  test('صفحة الدخول الموحّدة تعرض الحقول الأساسية', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('input[type="email"], input[autocomplete="username"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
    await expect(page.getByRole('button', { name: /دخول|Sign in|سائن/i })).toBeVisible()
  })

  test('رابط الصفحة التعريفية من الدخول', async ({ page }) => {
    await page.goto('/login')
    const marketing = page.getByRole('link', { name: /تعريفية|Marketing|landing/i })
    await expect(marketing.first()).toBeVisible()
    await marketing.first().click()
    await expect(page).toHaveURL(/\/landing/)
  })

  test('دخول مشغّل المنصة يحمّل النموذج', async ({ page }) => {
    await page.goto('/platform/login')
    await expect(page.locator('input[type="email"], input[type="text"]').first()).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
  })

  test('نسيت كلمة المرور', async ({ page }) => {
    await page.goto('/forgot-password')
    await expect(page.locator('input[type="email"]')).toBeVisible()
  })

  test('مسار غير معروف يعرض صفحة 404', async ({ page }) => {
    await page.goto('/__playwright_not_found__/x')
    await expect(page.getByRole('heading', { level: 1, name: '404' })).toBeVisible()
  })
})
