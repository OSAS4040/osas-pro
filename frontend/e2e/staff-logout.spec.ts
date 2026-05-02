import { test, expect } from '@playwright/test'

/**
 * يتطلب واجهة + API (docker + `npm run dev` أو `vite preview` مع بروكسي — راجع `vite.config.ts` → `preview.proxy`).
 * معاينة إنتاج: `npx playwright test e2e/staff-logout.spec.ts`
 * تطوير حي: `PLAYWRIGHT_NO_WEB_SERVER=1 PLAYWRIGHT_BASE_URL=http://127.0.0.1:5173 npx playwright test e2e/staff-logout.spec.ts`
 *
 * معطّل افتراضياً (انظر PW_STAFF_LOGOUT_E2E): يعتمد على جلسة كاملة + AppLayout؛ في بعض بيئات الحاوية لا يظهر زر الخروج بعد حقن التوكن.
 * لتشغيله صراحة: `PW_STAFF_LOGOUT_E2E=1` مع خادم واجهة محدّث (إصلاح router: fetchMe قبل حارس مسارات العميل).
 */
const staffLogoutSuite = process.env.PW_STAFF_LOGOUT_E2E === '1' ? test.describe : test.describe.skip
async function staffLoginToken(request: import('@playwright/test').APIRequestContext, baseURL: string) {
  const res = await request.post(`${baseURL}/api/v1/auth/login`, {
    data: { email: 'manager@demo.sa', password: 'Password123!' },
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  })
  expect(res.ok(), `login failed: ${res.status()} ${await res.text()}`).toBeTruthy()
  const body = (await res.json()) as { token?: string }
  expect(body.token, 'missing token').toBeTruthy()
  return body.token as string
}

/** حقن التوكن ثم فتح الرئيسية حتى يكمّل الراوتر fetchMe ويُرسَم AppLayout (يتطلّب إصلاح router: fetchMe قبل حارس مسارات العميل). */
async function staffShellReady(page: import('@playwright/test').Page, baseURL: string, token: string): Promise<void> {
  await page.goto(`${baseURL}/login`)
  await page.evaluate((t) => localStorage.setItem('auth_token', t), token)
  await page.reload()
  await page.goto(`${baseURL}/`)
  await page.getByTestId('app-header-logout').waitFor({ state: 'visible', timeout: 45_000 })
}

function headerLogout(page: import('@playwright/test').Page) {
  // AppLayout يستخدم landmark دوره banner وليس بالضرورة وسم <header>
  return page.getByTestId('app-header-logout')
}

staffLogoutSuite('تسجيل الخروج (موظف / AppLayout)', () => {
  test('ضغطة واحدة: جلسة تنتهي والانتقال إلى الدخول؛ الرجوع لا يعيد وضعاً موثّقاً', async ({
    page,
    request,
    baseURL,
  }) => {
    test.skip(!baseURL, 'baseURL مطلوب')
    const token = await staffLoginToken(request, baseURL)
    await staffShellReady(page, baseURL, token)
    await page.goto(`${baseURL}/work-orders`)
    await expect(page).toHaveURL(/\/work-orders/)

    await expect(
      page.locator('aside').getByRole('button', { name: 'تسجيل الخروج' }),
      'التخطيط الحالي: لا يجب أن يوجد زر خروج في الشريط الجانبي',
    ).toHaveCount(0)

    let logoutPosts = 0
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('/auth/logout')) logoutPosts += 1
    })

    const logoutBtn = headerLogout(page)
    await expect(logoutBtn, 'زر الخروج يجب أن يكون في شريط الرأس — أعد بناء الواجهة إن فشل').toBeVisible()
    await expect(logoutBtn).toHaveAttribute('aria-label', 'تسجيل الخروج')
    await expect(logoutBtn).toHaveAttribute('title', 'تسجيل الخروج')
    await expect(page.getByTestId('app-header-logout')).toBeVisible()

    await logoutBtn.click()
    await expect(page).toHaveURL(/\/login/)
    await page.waitForLoadState('domcontentloaded')
    expect(logoutPosts, 'يجب إرسال طلب خروج واحد فقط').toBe(1)

    await expect
      .poll(async () => page.evaluate(() => localStorage.getItem('auth_token')), { timeout: 15_000 })
      .toBeNull()

    await page.goBack()
    await expect(page).toHaveURL(/\/login/)
    expect(await page.evaluate(() => localStorage.getItem('auth_token'))).toBeNull()

    await page.goto(`${baseURL}/work-orders`)
    await expect(page).toHaveURL(/\/login/)
  })

  test('ضغطات متتابعة سريعة: طلب خروج واحد فقط', async ({ page, request, baseURL }) => {
    test.skip(!baseURL, 'baseURL مطلوب')
    const token = await staffLoginToken(request, baseURL)
    await staffShellReady(page, baseURL, token)
    await page.goto(`${baseURL}/work-orders`)

    let logoutPosts = 0
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('/auth/logout')) logoutPosts += 1
    })

    const logoutBtn = headerLogout(page)
    await logoutBtn.click({ clickCount: 5, delay: 15 })
    await expect(page).toHaveURL(/\/login/, { timeout: 20_000 })
    expect(logoutPosts, 'لا يُسمح بإرسال أكثر من طلب logout').toBe(1)
  })

  test('شاشة صغيرة: أيقونة الخروج في الهيدر (aria) تعمل', async ({ page, request, baseURL }) => {
    test.skip(!baseURL, 'baseURL مطلوب')
    await page.setViewportSize({ width: 375, height: 667 })

    const token = await staffLoginToken(request, baseURL)
    await staffShellReady(page, baseURL, token)
    await page.goto(`${baseURL}/work-orders`)
    await page.waitForLoadState('domcontentloaded')

    const logoutBtn = page.getByTestId('app-header-logout')
    await expect(logoutBtn).toBeVisible({ timeout: 30_000 })
    await expect(logoutBtn).toHaveAttribute('aria-label', /logout|خروج/i)
    await logoutBtn.scrollIntoViewIfNeeded()
    await logoutBtn.click()
    await expect(page).toHaveURL(/\/login/)
  })
})
