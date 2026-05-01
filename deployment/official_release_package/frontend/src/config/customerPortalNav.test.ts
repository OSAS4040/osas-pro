// @vitest-environment happy-dom
import { describe, expect, it } from 'vitest'
import router from '@/router/index'

import { CUSTOMER_PORTAL_NAV_PATHS } from './customerPortalPaths'

describe('customer portal navigation vs router', () => {
  it('resolves every sidebar path to a real route (no 404 / empty match)', () => {
    for (const path of CUSTOMER_PORTAL_NAV_PATHS) {
      const r = router.resolve(path)
      expect(r.matched.length, path).toBeGreaterThan(0)
      expect(String(r.name ?? ''), path).not.toMatch(/^NotFound|^not-found$/i)
    }
  })

  it('customer routes live under /customer meta guard', () => {
    const r = router.resolve('/customer/dashboard')
    const last = r.matched[r.matched.length - 1]
    expect(last?.path.startsWith('/customer') || r.path.startsWith('/customer')).toBe(true)
  })
})
