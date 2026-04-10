import { test, expect } from '@playwright/test'

test.describe('صفحة الهبوط', () => {
  test('تحميل /landing مع عنوان رئيسي ودعوة للدخول', async ({ page }) => {
    await page.goto('/landing')
    await expect(page.getByRole('heading', { level: 1 })).toContainText('نظام تشغيل أعمالك')
    await expect(page.getByRole('link', { name: 'دخول المنصة' })).toBeVisible()
  })

  test('الاسم البديل /asas-pro يعرض نفس الصفحة', async ({ page }) => {
    await page.goto('/asas-pro')
    await expect(page.getByRole('heading', { level: 1 })).toContainText('نظام تشغيل أعمالك')
  })

  test('التنقل من الهبوط إلى صفحة الدخول', async ({ page }) => {
    await page.goto('/landing')
    await page.getByRole('link', { name: 'دخول المنصة' }).click()
    await expect(page).toHaveURL(/\/login$/)
    await expect(page.locator('input[type="email"], input[autocomplete="username"]')).toBeVisible()
  })
})
