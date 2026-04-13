import { test, expect } from '@playwright/test'

/**
 * Readiness gate — smoke paths that do not require a live API (guest / shell).
 * Authenticated hub flows: use env PW_LOGIN_EMAIL, PW_LOGIN_PASSWORD, PW_COMPANY_ID, PW_CUSTOMER_ID
 * and run `readiness-gate-authenticated.spec.ts` when available.
 */
test.describe('Readiness gate (public shell)', () => {
  test('login page — fields and primary action', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('input[type="email"], input[autocomplete="username"]').first()).toBeVisible()
    await expect(page.locator('input[type="password"]').first()).toBeVisible()
    await expect(page.getByRole('button', { name: /دخول|Sign in|Login/i })).toBeVisible()
  })

  test('reports hub shell loads (guest redirects to login)', async ({ page }) => {
    await page.goto('/reports')
    await expect(page).toHaveURL(/login/)
  })

  test('global operations feed shell (guest redirects)', async ({ page }) => {
    await page.goto('/operations/global-feed')
    await expect(page).toHaveURL(/login/)
  })
})
