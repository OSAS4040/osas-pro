/// <reference types="node" />
import { test, expect, Page } from '@playwright/test';

const LOGIN_EMAIL = process.env.PW_LOGIN_EMAIL ?? '';
const LOGIN_PASSWORD = process.env.PW_LOGIN_PASSWORD ?? '';

test.describe('Production real flow', () => {
  async function login(page: Page) {
    await page.goto('/login');
    await page.locator('input[autocomplete="username"]').first().fill(LOGIN_EMAIL)
    await page.locator('input[type="password"], input[autocomplete="current-password"]').first().fill(LOGIN_PASSWORD)
    await page.getByRole('button', { name: /login|دخول|تسجيل/i }).click();
    await page.waitForLoadState('networkidle');
  }

  test('full navigation test', async ({ page }) => {
    test.skip(!LOGIN_EMAIL || !LOGIN_PASSWORD, 'يتطلب PW_LOGIN_EMAIL و PW_LOGIN_PASSWORD في البيئة')
    await login(page);

    await page.goto('/');
    await expect(page).not.toHaveURL(/login/);

    await page.goto('/customers');
    await page.goto('/vehicles');
    await page.goto('/work-orders');
    await page.goto('/invoices');
  });
});