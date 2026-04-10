/**
 * يلتقط لقطات شاشة PNG من الواجهة الفعلية لمجلد public/landing/showcase/
 * (نفس أسماء الجذوع: pos, workorder, inventory, reports, billing, intelligence).
 *
 * المتطلبات: تشغيل الواجهة + واجهة API مع بيانات تجريبية (مثلاً DemoCompanySeeder).
 * تشغيل: من مجلد frontend بعد npm install:
 *   npx playwright install chromium
 *   npm run capture:showcase
 *
 * متغيرات اختيارية:
 *   SHOWCASE_BASE_URL — افتراضي http://127.0.0.1:5173
 *   SHOWCASE_EMAIL / SHOWCASE_PASSWORD — افتراضي owner@demo.sa / password
 */
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { chromium } from 'playwright'

const __dirname = dirname(fileURLToPath(import.meta.url))
const outDir = join(__dirname, '..', 'public', 'landing', 'showcase')
const baseURL = process.env.SHOWCASE_BASE_URL?.replace(/\/$/, '') || 'http://127.0.0.1:5173'
const email = process.env.SHOWCASE_EMAIL || 'owner@demo.sa'
const password = process.env.SHOWCASE_PASSWORD || 'password'

const shots = [
  { path: '/pos', stem: 'pos' },
  { path: '/work-orders', stem: 'workorder' },
  { path: '/inventory', stem: 'inventory' },
  { path: '/reports', stem: 'reports' },
  { path: '/zatca', stem: 'billing' },
  { path: '/internal/intelligence', stem: 'intelligence' },
]

const browser = await chromium.launch({ headless: true })
try {
  const page = await browser.newPage({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
  })

  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded', timeout: 60000 })
  await page.locator('input[type="email"]').fill(email)
  await page.locator('input[type="password"]').first().fill(password)
  await page.locator('button[type="submit"]').click()

  await page.waitForURL(
    (u) => !u.pathname.includes('login'),
    { timeout: 60000 },
  )

  for (const { path: p, stem } of shots) {
    const url = `${baseURL}${p}`
    try {
      const res = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 })
      if (!res || !res.ok()) {
        console.warn(`Skip ${stem}: HTTP ${res?.status()} ${url}`)
        continue
      }
      await new Promise((r) => setTimeout(r, 800))
      const outPath = join(outDir, `${stem}.png`)
      await page.screenshot({ path: outPath, type: 'png', fullPage: false })
      console.log('Wrote', outPath)
    } catch (e) {
      console.warn(`Skip ${stem}:`, e instanceof Error ? e.message : e)
    }
  }
} finally {
  await browser.close()
}
