/**
 * Phase 4 — QA hardening: /platform/* stability, direct entry, legacy redirects, emit wiring smoke.
 * Credentials: DemoPlatformAdminSeeder (see locale hints / Phase 2 spec).
 */
import { test, expect } from '@playwright/test'
import { loginAsPlatformOperator } from './helpers/platformAdminLogin'

/** مسار URL → محدد القسم الرئيسي في DOM */
const PLATFORM_ROUTE_SECTION: { path: string; sectionSelector: string }[] = [
  { path: '/platform/overview', sectionSelector: '#admin-section-overview' },
  { path: '/platform/governance', sectionSelector: '#admin-section-governance' },
  { path: '/platform/ops', sectionSelector: '#admin-section-ops' },
  { path: '/platform/companies', sectionSelector: '#admin-section-tenants' },
  { path: '/platform/customers', sectionSelector: '#admin-section-customers' },
  { path: '/platform/plans', sectionSelector: '#admin-section-plans' },
  { path: '/platform/operator-commands', sectionSelector: '#admin-section-operator-commands' },
  { path: '/platform/audit', sectionSelector: '#admin-section-audit' },
  { path: '/platform/finance', sectionSelector: '#admin-section-finance' },
  { path: '/platform/announcements', sectionSelector: '#admin-section-banner' },
  { path: '/platform/cancellations', sectionSelector: '#admin-section-cancellations' },
  { path: '/platform/support', sectionSelector: '[data-testid="platform-support-root"]' },
]

test.describe('Phase 4 — platform stability gate', () => {
  test('platform scroll diagnostics: no transparent overlay blocks wheel/page-down', async ({ page }) => {
    await loginAsPlatformOperator(page)
    const routes = ['/platform/overview', '/platform/finance', '/platform/companies', '/platform/audit']
    for (const path of routes) {
      await page.goto(path)
      await expect(page).toHaveURL(new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
      const main = page.getByTestId('platform-admin-main')
      await expect(main).toBeVisible({ timeout: 45_000 })

      // تأكيد عدم وجود طبقة شفافة تغطي الشاشة وتلتقط المؤشر.
      const blockers = await page.evaluate(() => {
        const viewportW = window.innerWidth
        const viewportH = window.innerHeight
        const all = Array.from(document.querySelectorAll<HTMLElement>('body *'))
        return all
          .filter((el) => {
            const s = window.getComputedStyle(el)
            if (s.pointerEvents === 'none') return false
            if (!(s.position === 'fixed' || s.position === 'absolute')) return false
            if (!(Number.parseFloat(s.opacity || '1') === 0)) return false
            const zi = Number.parseInt(s.zIndex || '0', 10)
            if (!Number.isFinite(zi) || zi < 10) return false
            const r = el.getBoundingClientRect()
            const coversViewport =
              r.width >= viewportW * 0.9 &&
              r.height >= viewportH * 0.9 &&
              r.top <= 0 &&
              r.left <= 0
            return coversViewport
          })
          .map((el) => ({ tag: el.tagName, cls: el.className }))
      })
      expect(blockers, `overlay blockers on ${path}: ${JSON.stringify(blockers)}`).toEqual([])

      const hasScroll = await main.evaluate((el) => el.scrollHeight > el.clientHeight + 40)
      if (!hasScroll) continue

      await main.evaluate((el) => { el.scrollTop = 0 })
      const start = await main.evaluate((el) => el.scrollTop)

      await page.mouse.wheel(0, 1000)
      await page.waitForTimeout(120)
      const afterWheel = await main.evaluate((el) => el.scrollTop)
      expect(afterWheel).toBeGreaterThan(start)

      await page.keyboard.press('PageDown')
      await page.waitForTimeout(120)
      const afterPageDown = await main.evaluate((el) => el.scrollTop)
      expect(afterPageDown).toBeGreaterThan(afterWheel)
    }
  })

  test('entry: /platform/overview shows layout and overview section', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/overview')
    await expect(page).toHaveURL(/\/platform\/overview/)
    await expect(page.getByTestId('platform-admin-root')).toBeVisible({ timeout: 45_000 })
    await expect(page.getByTestId('platform-admin-main')).toBeVisible()
    await expect(page.locator('#admin-section-overview')).toBeVisible()
    await expect(page.getByText(/مركز التحكم|المنصة/i).first()).toBeVisible()
    await page.reload()
    await expect(page.locator('#admin-section-overview')).toBeVisible({ timeout: 45_000 })
  })

  test('each primary /platform/* route renders the expected section (no blank main)', async ({ page }) => {
    await loginAsPlatformOperator(page)
    for (const { path, sectionSelector } of PLATFORM_ROUTE_SECTION) {
      await page.goto(path)
      await expect(page).toHaveURL(new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
      await expect(page.getByTestId('platform-admin-main')).toBeVisible({ timeout: 45_000 })
      await expect(page.locator(sectionSelector)).toBeVisible({ timeout: 30_000 })
    }
  })

  test('direct open: companies, finance, audit', async ({ page }) => {
    await loginAsPlatformOperator(page)
    for (const path of ['/platform/companies', '/platform/finance', '/platform/audit']) {
      await page.goto(path)
      await expect(page.getByTestId('platform-admin-main')).toBeVisible({ timeout: 45_000 })
      await expect(page).toHaveURL(new RegExp(path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
    }
  })

  test('legacy redirects: /admin and /admin/overview and hash → platform', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/admin')
    await expect(page).toHaveURL(/\/platform\/overview/, { timeout: 45_000 })

    await page.goto('/admin/overview')
    await expect(page).toHaveURL(/\/platform\/overview/, { timeout: 45_000 })

    await page.goto('/admin#admin-section-audit')
    await expect(page).toHaveURL(/\/platform\/audit/, { timeout: 45_000 })
  })

  test('section actions smoke: audit load + operator copy', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/audit')
    await expect(page.locator('#admin-section-audit')).toBeVisible({ timeout: 45_000 })
    await page.locator('#admin-section-audit').getByRole('button', { name: 'تحميل السجلات' }).click()

    await page.goto('/platform/operator-commands')
    await expect(page.locator('#admin-section-operator-commands')).toBeVisible({ timeout: 45_000 })
    const copyBtn = page.locator('#admin-section-operator-commands').getByRole('button', { name: 'نسخ' }).first()
    if (await copyBtn.isVisible()) {
      await copyBtn.click()
    }
  })

  test('companies oversight: snapshot + top panels + filters + drill-down', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/companies')
    await expect(page.locator('#admin-section-tenants')).toBeVisible({ timeout: 45_000 })

    await expect(page.getByText('إجمالي الشركات')).toBeVisible()
    await expect(page.getByText('أعلى الشركات إيرادًا')).toBeVisible()
    await expect(page.getByText('أعلى الشركات نموًا')).toBeVisible()
    await expect(page.getByText('أعلى الشركات مخاطر')).toBeVisible()
    await expect(page.getByText('تحتاج متابعة فورية')).toBeVisible()

    const search = page.getByPlaceholder('بحث باسم الشركة...')
    await expect(search).toBeVisible()

    const firstOpenBtn = page.locator('#admin-section-tenants').getByRole('button', { name: 'فتح ملف الشركة' }).first()
    await expect(firstOpenBtn).toBeVisible()
    await firstOpenBtn.click()

    await expect(page).toHaveURL(/\/platform\/companies\/\d+/, { timeout: 45_000 })
    await expect(page.getByText('إدارة المنصة — مركز تحكم الشركة')).toBeVisible()
  })

  test('company deep control: tabs switch without leaving platform context', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/companies')
    await expect(page.locator('#admin-section-tenants')).toBeVisible({ timeout: 45_000 })

    const firstOpenBtn = page.locator('#admin-section-tenants').getByRole('button', { name: 'فتح ملف الشركة' }).first()
    await expect(firstOpenBtn).toBeVisible()
    await firstOpenBtn.click()

    await expect(page).toHaveURL(/\/platform\/companies\/\d+/, { timeout: 45_000 })
    await expect(page.getByRole('button', { name: 'Overview' })).toBeVisible()

    await page.getByRole('button', { name: 'Finance' }).click()
    await expect(page.getByRole('heading', { name: 'Finance' })).toBeVisible()

    await page.getByRole('button', { name: 'Customers' }).click()
    await expect(page.getByRole('heading', { name: 'Customers' })).toBeVisible()

    await page.getByRole('button', { name: 'Vehicles' }).click()
    await expect(page.getByRole('heading', { name: 'Vehicles' })).toBeVisible()

    await page.getByRole('button', { name: 'Invoices' }).click()
    await expect(page.getByRole('heading', { name: 'Invoices' })).toBeVisible()
    await expect(page).toHaveURL(/\/platform\/companies\/\d+/, { timeout: 10_000 })
  })

  test('priority 5 flow: overview to company and back with breadcrumbs', async ({ page }) => {
    await loginAsPlatformOperator(page)
    await page.goto('/platform/overview')
    await expect(page.getByRole('link', { name: 'عرض الشركات' }).first()).toBeVisible({ timeout: 45_000 })
    await page.getByRole('link', { name: 'عرض الشركات' }).first().click()

    await expect(page).toHaveURL(/\/platform\/companies/, { timeout: 45_000 })
    await expect(page.locator('nav[aria-label="breadcrumb"]').getByRole('link', { name: 'إدارة المنصة' }).first()).toBeVisible()

    const firstOpenBtn = page.locator('#admin-section-tenants').getByRole('button', { name: 'فتح ملف الشركة' }).first()
    await firstOpenBtn.click()
    await expect(page).toHaveURL(/\/platform\/companies\/\d+/, { timeout: 45_000 })

    await expect(page.locator('nav[aria-label="breadcrumb"]').getByRole('link', { name: 'الشركات' })).toBeVisible()
    await page.getByRole('button', { name: 'Invoices' }).click()
    await expect(page.getByRole('heading', { name: 'Invoices' })).toBeVisible()

    await page.getByRole('link', { name: 'العودة إلى الشركات' }).click()
    await expect(page).toHaveURL(/\/platform\/companies/, { timeout: 45_000 })
  })
})
