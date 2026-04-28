/// <reference types="node" />
import { test, expect, Page } from '@playwright/test'

const CLIENT_EMAIL = process.env.PW_SUB_CLIENT_EMAIL ?? process.env.PW_LOGIN_EMAIL ?? ''
const CLIENT_PASSWORD = process.env.PW_SUB_CLIENT_PASSWORD ?? process.env.PW_LOGIN_PASSWORD ?? ''
const ADMIN_EMAIL = process.env.PW_SUB_ADMIN_EMAIL ?? ''
const ADMIN_PASSWORD = process.env.PW_SUB_ADMIN_PASSWORD ?? ''

async function login(page: Page, email: string, password: string) {
  await page.goto('/login')
  await page.locator('input[autocomplete="username"]').first().fill(email)
  await page.locator('input[type="password"], input[autocomplete="current-password"]').first().fill(password)
  await page.getByRole('button', { name: /login|دخول|تسجيل/i }).click()
  await page.waitForLoadState('networkidle')
}

test.describe('Subscriptions smoke flows', () => {
  test('client flow: subscription, payment order, receipt upload', async ({ page }) => {
    test.skip(!CLIENT_EMAIL || !CLIENT_PASSWORD, 'requires PW_SUB_CLIENT_EMAIL/PW_SUB_CLIENT_PASSWORD')
    await login(page, CLIENT_EMAIL, CLIENT_PASSWORD)

    await page.goto('/subscription')
    await expect(page.getByRole('heading', { name: /الاشتراك/i })).toBeVisible()

    await page.goto('/subscription/payment')
    await expect(page.getByRole('heading', { name: /الدفع/i })).toBeVisible()

    const createOrderResp = page.waitForResponse((resp) =>
      resp.url().includes('/api/v1/subscriptions/payment-orders') && resp.request().method() === 'POST'
    )
    await page.getByRole('button', { name: /إنشاء طلب دفع/i }).click()
    await expect(await createOrderResp).toBeTruthy()
  })

  test('admin flow: review queue page loads and actions visible', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASSWORD, 'requires PW_SUB_ADMIN_EMAIL/PW_SUB_ADMIN_PASSWORD')
    await page.goto('/platform/login')
    await page.locator('input[autocomplete="username"], input[type="email"]').first().fill(ADMIN_EMAIL)
    await page.locator('input[type="password"], input[autocomplete="current-password"]').first().fill(ADMIN_PASSWORD)
    await page.getByRole('button', { name: /login|دخول|تسجيل/i }).click()
    await page.waitForLoadState('networkidle')

    await page.goto('/admin/subscriptions')
    await expect(page.getByRole('heading', { name: /Review Queue/i })).toBeVisible()
  })

  test('lifecycle visibility: current status available', async ({ page, request }) => {
    test.skip(!CLIENT_EMAIL || !CLIENT_PASSWORD, 'requires PW_SUB_CLIENT_EMAIL/PW_SUB_CLIENT_PASSWORD')
    await login(page, CLIENT_EMAIL, CLIENT_PASSWORD)
    const token = await page.evaluate(() => localStorage.getItem('auth_token'))
    test.skip(!token, 'auth token missing')

    const resp = await request.get('/api/v1/subscriptions/current', {
      headers: { Authorization: `Bearer ${token}` },
    })
    expect(resp.ok()).toBeTruthy()
    const body = await resp.json()
    expect(body?.data).toBeTruthy()
    expect(typeof body?.data).toBe('object')
  })
})

