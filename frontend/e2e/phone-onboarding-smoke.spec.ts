import { test, expect } from '@playwright/test'

test.describe('Phone OTP onboarding (UI smoke)', () => {
  test('entry page renders and links back to email login', async ({ page }) => {
    await page.goto('/phone')
    await expect(page.getByRole('heading', { name: /دخول برقم الجوال/ })).toBeVisible()
    await expect(page.getByPlaceholder('05xxxxxxxx')).toBeVisible()
    await expect(page.getByRole('link', { name: /الدخول بالبريد وكلمة المرور/ })).toBeVisible()
  })

  test('marketing landing hides phone-onboarding return strip for guests', async ({ page }) => {
    await page.goto('/landing')
    await expect(page.getByTestId('landing-phone-onboarding-return')).toHaveCount(0)
  })
})
