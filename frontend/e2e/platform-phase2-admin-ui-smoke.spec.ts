/**
 * Phase 2 closeout — UI smoke for /admin (read-only verification).
 * Credentials match DemoPlatformAdminSeeder / i18n dev hints (public in locale files).
 * Set PLAYWRIGHT_BASE_URL (e.g. http://127.0.0.1) and PLAYWRIGHT_NO_WEB_SERVER=1 when a dev server is already running.
 */
import { test, expect } from '@playwright/test'

const PLATFORM_EMAIL = process.env.PW_PLATFORM_EMAIL ?? 'platform-demo@osas.sa'
const PLATFORM_PASSWORD = process.env.PW_PLATFORM_PASSWORD ?? '12345678'
const TENANT_EMAIL = process.env.PW_TENANT_EMAIL ?? 'owner@demo.sa'
const TENANT_PASSWORD = process.env.PW_TENANT_PASSWORD ?? 'password'

test.describe('Phase 2 — platform admin UI smoke', () => {
  test('platform operator: /admin shows executive dashboard; survives refresh', async ({ page }) => {
    await page.goto('/platform/login')
    await page.locator('input[type="email"], input[autocomplete="username"]').first().fill(PLATFORM_EMAIL)
    await page.locator('input[type="password"]').first().fill(PLATFORM_PASSWORD)
    await page.getByRole('button', { name: /دخول|Sign in|Login/i }).click()
    await page.waitForLoadState('networkidle')

    await page.goto('/admin')
    await expect(page.getByText(/لوحة قيادة المنصة/i).first()).toBeVisible({ timeout: 45_000 })
    await expect(page.getByText(/Executive SaaS Control Center|قراءة فقط/i).first()).toBeVisible()

    await expect(page.locator('section').filter({ hasText: /تقدير صحة|Health|الصحة/i }).first()).toBeVisible({
      timeout: 30_000,
    })
    await expect(page.getByText(/KPI|مؤشرات|Insights|رؤى|Alerts|تنبيهات|Quick|سريع/i).first()).toBeVisible({
      timeout: 30_000,
    })

    await page.reload()
    await expect(page.getByText(/لوحة قيادة المنصة/i).first()).toBeVisible({ timeout: 45_000 })
  })

  test('tenant staff: /admin redirects away (no platform access)', async ({ page }) => {
    await page.goto('/login')
    await page.locator('input[type="email"], input[autocomplete="username"]').first().fill(TENANT_EMAIL)
    await page.locator('input[type="password"]').first().fill(TENANT_PASSWORD)
    await page.getByRole('button', { name: /دخول|Sign in|Login/i }).click()
    await page.waitForLoadState('networkidle')

    await page.goto('/')
    await page.goto('/admin')
    await expect(page).not.toHaveURL(/\/admin\/?$/)
  })
})
