import { test, expect, type Page } from '@playwright/test'

const EMAIL = process.env.PW_LOGIN_EMAIL ?? ''
const PASSWORD = process.env.PW_LOGIN_PASSWORD ?? ''
const COMPANY_ID = process.env.PW_COMPANY_ID ?? ''
const CUSTOMER_ID = process.env.PW_CUSTOMER_ID ?? ''

async function staffLogin(page: Page): Promise<void> {
  await page.goto('/login')
  await page.locator('input[type="email"], input[autocomplete="username"]').first().fill(EMAIL)
  await page.locator('input[type="password"]').first().fill(PASSWORD)
  await page.getByRole('button', { name: /دخول|Sign in|Login/i }).click()
  await page.waitForLoadState('networkidle')
}

test.describe('Readiness gate (authenticated)', () => {
  test('post-login: company profile when PW_COMPANY_ID set', async ({ page }) => {
    test.skip(!EMAIL || !PASSWORD, 'Set PW_LOGIN_EMAIL and PW_LOGIN_PASSWORD.')
    test.skip(!COMPANY_ID, 'Set PW_COMPANY_ID for company hub E2E.')
    await staffLogin(page)
    await page.goto(`/companies/${COMPANY_ID}`)
    await expect(page.locator('.app-shell-page')).toBeVisible()
    await expect(page.getByText(/مركز الشركة|Company hub/i).first()).toBeVisible({ timeout: 20_000 })
  })

  test('post-login: customer profile when PW_CUSTOMER_ID set', async ({ page }) => {
    test.skip(!EMAIL || !PASSWORD, 'Set PW_LOGIN_EMAIL and PW_LOGIN_PASSWORD.')
    test.skip(!CUSTOMER_ID, 'Set PW_CUSTOMER_ID for customer hub E2E.')
    await staffLogin(page)
    await page.goto(`/customers/${CUSTOMER_ID}`)
    await expect(page.locator('.app-shell-page')).toBeVisible()
    await expect(page.getByText(/مركز العميل|Customer hub/i).first()).toBeVisible({ timeout: 20_000 })
  })

  test('post-login: reports and operations feed reachable', async ({ page }) => {
    test.skip(!EMAIL || !PASSWORD, 'Set PW_LOGIN_EMAIL and PW_LOGIN_PASSWORD.')
    await staffLogin(page)
    await page.goto('/reports')
    await expect(page).not.toHaveURL(/\/login/)
    await page.goto('/operations/global-feed')
    await expect(page).not.toHaveURL(/\/login/)
  })
})
