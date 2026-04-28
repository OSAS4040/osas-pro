/**
 * Final Blocking Closure — Phase 4
 * يتطلب خادماً يستجيب لـ /api (بروكسي Vite) وحساب منصة (DemoPlatformAdminSeeder أو ما يعادله).
 */
import { test, expect } from '@playwright/test'
import { loginAsPlatformOperator } from './helpers/platformAdminLogin'

test.describe('Phase 4 — Final Platform Blocking E2E', () => {
  test.describe.configure({ timeout: 180_000 })

  test.use({
    permissions: ['clipboard-read', 'clipboard-write'],
  })

  test('full blocking path: navigation, actions, legacy hashes, no fatal errors', async ({ page }) => {
    const pageErrors: string[] = []
    page.on('pageerror', (err) => {
      pageErrors.push(err.message)
    })

    await loginAsPlatformOperator(page)

    await page.goto('/platform/overview')
    await expect(page).toHaveURL(/\/platform\/overview/)
    await expect(page.getByTestId('platform-admin-root')).toBeVisible({ timeout: 60_000 })
    await expect(page.getByTestId('platform-admin-main')).toBeVisible()
    await expect(page.locator('#admin-section-overview')).toBeVisible({ timeout: 45_000 })

    const navPaths = [
      '/platform/companies',
      '/platform/operator-commands',
      '/platform/governance',
      '/platform/finance',
      '/platform/audit',
      '/platform/support',
      '/platform/announcements',
      '/platform/cancellations',
    ] as const
    for (const path of navPaths) {
      await page.goto(path)
      await expect(page).toHaveURL(new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
      await expect(page.getByTestId('platform-admin-main')).toBeVisible({ timeout: 60_000 })
    }

    for (const path of ['/platform/companies', '/platform/finance', '/platform/audit']) {
      await page.goto(path)
      await expect(page.getByTestId('platform-admin-main')).toBeVisible({ timeout: 60_000 })
      await expect(page).toHaveURL(new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
    }

    await page.goto('/platform/operator-commands')
    await expect(page.locator('#admin-section-operator-commands')).toBeVisible({ timeout: 45_000 })
    const copyBtn = page.locator('#admin-section-operator-commands').getByRole('button', { name: 'نسخ' }).first()
    if (await copyBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
      await copyBtn.click()
    }

    await page.goto('/platform/finance')
    await expect(page.locator('#admin-section-finance')).toBeVisible({ timeout: 45_000 })
    const finSelect = page.locator('#admin-section-finance select').first()
    if (await finSelect.isVisible({ timeout: 5_000 }).catch(() => false)) {
      await finSelect.selectOption('pending_platform_review')
    }
    const financeDecisionBtn = page.locator('#admin-section-finance').getByRole('button', { name: 'تحديث القرار' }).first()
    if (await financeDecisionBtn.isVisible({ timeout: 15_000 }).catch(() => false)) {
      await financeDecisionBtn.click()
      await expect(page.getByRole('heading', { name: /قرار مالي/ })).toBeVisible({ timeout: 15_000 })
      await page.getByRole('button', { name: 'إلغاء' }).click()
      await expect(page.getByRole('heading', { name: /قرار مالي/ })).toBeHidden({ timeout: 10_000 })
    }

    await page.goto('/platform/audit')
    await expect(page.locator('#admin-section-audit')).toBeVisible({ timeout: 45_000 })
    await page.locator('#admin-section-audit').getByRole('button', { name: 'تحميل السجلات' }).click()

    await page.goto('/platform/governance')
    const gov = page.locator('#admin-section-governance')
    await expect(gov).toBeVisible({ timeout: 45_000 })
    await expect(async () => {
      const t = await gov.innerText()
      expect(t).toMatch(/بيئة التشغيل:|تعذّر جلب/)
      expect(t).toMatch(/رقم الإصدار:|تعذّر جلب/)
    }).toPass({ timeout: 90_000 })

    await page.goto('/platform/companies')
    await expect(page.locator('#admin-section-tenants')).toBeVisible({ timeout: 45_000 })
    const tenantBtn = page.locator('#admin-section-tenants').getByRole('button', { name: /تشغيل \/ باقة/ }).first()
    if (await tenantBtn.isVisible({ timeout: 15_000 }).catch(() => false)) {
      await tenantBtn.click()
      await expect(page.getByRole('heading', { name: /تشغيل —/ })).toBeVisible({ timeout: 20_000 })
      await page.getByRole('heading', { name: /تشغيل —/ }).locator('..').getByRole('button').first().click()
      await expect(page.getByRole('heading', { name: /تشغيل —/ })).toBeHidden({ timeout: 10_000 })
    }

    await page.goto('/admin')
    await expect(page).toHaveURL(/\/platform\/overview/, { timeout: 60_000 })
    await page.goto('/admin/overview')
    await expect(page).toHaveURL(/\/platform\/overview/, { timeout: 60_000 })

    const legacyHashes: { hash: string; url: RegExp }[] = [
      { hash: '#admin-section-finance', url: /\/platform\/finance/ },
      { hash: '#admin-section-tenants', url: /\/platform\/companies/ },
      { hash: '#admin-section-governance', url: /\/platform\/governance/ },
    ]
    for (const { hash, url } of legacyHashes) {
      await page.goto(`/admin${hash}`)
      await expect(page).toHaveURL(url, { timeout: 60_000 })
    }

    await page.goto('/platform/overview')
    await page.reload()
    await expect(page.locator('#admin-section-overview')).toBeVisible({ timeout: 60_000 })

    expect(pageErrors, `pageerror: ${pageErrors.join(' | ')}`).toEqual([])
  })
})
