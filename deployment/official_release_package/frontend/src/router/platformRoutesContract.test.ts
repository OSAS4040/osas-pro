// @vitest-environment happy-dom
import { describe, expect, it, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { allPlatformControlPlanePricingRouteNames, platformAdminNavItems } from '@/config/platformAdminNav'

describe('platform route registry (Vue Router)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('resolves every platform nav route name to a non-empty matched record', async () => {
    const { default: router } = await import('@/router')
    for (const item of platformAdminNavItems) {
      const r = router.resolve({ name: item.routeName })
      expect(r.matched.length, item.routeName).toBeGreaterThan(0)
      expect(r.path.startsWith('/platform/'), `${item.routeName} → ${r.path}`).toBe(true)
    }
  })

  it('resolves control-plane pricing & provider sidebar route names', async () => {
    const { default: router } = await import('@/router')
    for (const routeName of allPlatformControlPlanePricingRouteNames()) {
      const r = router.resolve({ name: routeName })
      expect(r.matched.length, routeName).toBeGreaterThan(0)
      expect(r.path.startsWith('/platform/'), `${routeName} → ${r.path}`).toBe(true)
    }
  })

  it('registers /platform/overview as the named default for the platform layout', async () => {
    const { default: router } = await import('@/router')
    const r = router.getRoutes().find((x) => x.name === 'platform-overview')
    expect(r?.path).toBe('/platform/overview')
  })

  it('legacy /admin/overview route record carries static redirect to /platform/overview', async () => {
    const { default: router } = await import('@/router')
    const rec = router.getRoutes().find((x) => x.path === '/admin/overview')
    expect(rec?.redirect).toBe('/platform/overview')
  })

  it('legacy /admin route uses a redirect function (hash → /platform/* at navigation time)', async () => {
    const { default: router } = await import('@/router')
    const rec = router.getRoutes().find((x) => x.path === '/admin')
    expect(typeof rec?.redirect).toBe('function')
  })
})
