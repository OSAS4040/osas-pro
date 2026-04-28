import { defineConfig, devices } from '@playwright/test'

/**
 * E2E ضد بناء إنتاج محلي (vite preview).
 * - تشغيل تلقائي للبناء + المعاينة ما لم يُضبط PLAYWRIGHT_NO_WEB_SERVER=1 أو يكن الخادم يعمل على المنفذ.
 * - PLAYWRIGHT_BASE_URL لتجاوز عنوان الاختبار (مثلاً بيئة staging).
 */
const previewPort = Number(process.env.PLAYWRIGHT_PREVIEW_PORT ?? 4173)
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? `http://127.0.0.1:${previewPort}`

export default defineConfig({
  testDir: 'e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: [['list']],
  timeout: 60_000,
  expect: { timeout: 15_000 },
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    locale: 'ar-SA',
  },
  /**
   * مسارات المنصة تستخدم نفس حساب التجربة وجلسة Sanctum؛ التشغيل المتوازي لها يسبب تداخلاً.
   * مشروع منفصل serial + عامّ واحد لبقية الـ E2E.
   */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
      testIgnore: /platform-phase2-admin-ui-smoke\.spec|platform-phase4-/,
    },
    {
      name: 'chromium-platform-gate',
      use: { ...devices['Desktop Chrome'] },
      testMatch: /platform-phase2-admin-ui-smoke\.spec|platform-phase4-/,
      fullyParallel: false,
      workers: 1,
    },
  ],
  webServer:
    process.env.PLAYWRIGHT_NO_WEB_SERVER === '1'
      ? undefined
      : {
          command: `npm run build && npx vite preview --host 127.0.0.1 --port ${previewPort} --strictPort`,
          url: baseURL,
          reuseExistingServer: !process.env.CI,
          timeout: 240_000,
          stdout: 'pipe',
          stderr: 'pipe',
        },
})
