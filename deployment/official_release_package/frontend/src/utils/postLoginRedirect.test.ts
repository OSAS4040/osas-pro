import { describe, expect, it } from 'vitest'
import type { LoginAccountContextPayload } from '@/types/accountContext'
import { sanitizeInternalPath, isPathConsistentWithAccountContext, resolvePostLoginTarget } from '@/utils/postLoginRedirect'

function ctx(partial: Partial<LoginAccountContextPayload> & Pick<LoginAccountContextPayload, 'principal_kind' | 'home_route_hint' | 'guard_hint'>): LoginAccountContextPayload {
  return {
    user_id: 1,
    company_id: 1,
    customer_id: null,
    role: 'owner',
    requires_context_selection: false,
    display_context: {},
    ...partial,
  }
}

describe('sanitizeInternalPath', () => {
  it('rejects open redirects and schemes', () => {
    expect(sanitizeInternalPath('//evil.com')).toBeNull()
    expect(sanitizeInternalPath('https://x')).toBeNull()
    expect(sanitizeInternalPath('/ok/path')).toBe('/ok/path')
  })
})

describe('isPathConsistentWithAccountContext', () => {
  it('restricts standalone platform employee to /platform and /admin surfaces', () => {
    const c = ctx({
      principal_kind: 'platform_employee',
      home_route_hint: '/platform/overview',
      guard_hint: 'platform',
      company_id: null,
    })
    expect(isPathConsistentWithAccountContext('/platform/overview', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/admin/qa', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/work-orders', c)).toBe(false)
  })

  it('allows platform employee with tenant anchor to use staff routes', () => {
    const c = ctx({
      principal_kind: 'platform_employee',
      home_route_hint: '/work-orders',
      guard_hint: 'staff',
      company_id: 9,
    })
    expect(isPathConsistentWithAccountContext('/work-orders', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/admin', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/platform/companies', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/fleet-portal', c)).toBe(false)
  })

  it('allows tenant staff operational routes but not other portals', () => {
    const c = ctx({ principal_kind: 'tenant_user', home_route_hint: '/pos', guard_hint: 'staff' })
    expect(isPathConsistentWithAccountContext('/pos', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/admin', c)).toBe(false)
    expect(isPathConsistentWithAccountContext('/platform/overview', c)).toBe(false)
    expect(isPathConsistentWithAccountContext('/fleet-portal', c)).toBe(false)
  })

  it('allows customer portal roots for customer_user', () => {
    const c = ctx({ principal_kind: 'customer_user', home_route_hint: '/customer/dashboard', guard_hint: 'customer' })
    expect(isPathConsistentWithAccountContext('/customer/dashboard', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/fleet-portal', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/admin', c)).toBe(false)
  })

  it('allows onboarding hub only when guard is onboarding', () => {
    const c = ctx({ principal_kind: 'unknown', home_route_hint: '/phone/onboarding', guard_hint: 'onboarding' })
    expect(isPathConsistentWithAccountContext('/phone/onboarding/type', c)).toBe(true)
    expect(isPathConsistentWithAccountContext('/admin', c)).toBe(false)
  })
})

describe('resolvePostLoginTarget', () => {
  it('prefers validated redirect query over hint when allowed', () => {
    const accountContext = ctx({ principal_kind: 'tenant_user', home_route_hint: '/', guard_hint: 'staff' })
    const path = resolvePostLoginTarget({
      accountContext,
      registrationFlow: null,
      registrationStage: null,
      accountType: null,
      portalHomeFromRole: '/',
      redirectQuery: '/work-orders',
    })
    expect(path).toBe('/work-orders')
  })

  it('uses phone onboarding resolver when flow active', () => {
    const path = resolvePostLoginTarget({
      accountContext: ctx({ principal_kind: 'unknown', home_route_hint: '/', guard_hint: 'onboarding' }),
      registrationFlow: {
        onboarding_active: true,
        needs_account_type: true,
        needs_basic_profile: false,
        company_pending_review: false,
      },
      registrationStage: null,
      accountType: null,
      portalHomeFromRole: '/',
      redirectQuery: '/work-orders',
    })
    expect(path).toBe('/phone/onboarding/type')
  })

  it('falls back to portalHome when hint is inconsistent', () => {
    const path = resolvePostLoginTarget({
      accountContext: ctx({ principal_kind: 'tenant_user', home_route_hint: '/admin', guard_hint: 'staff' }),
      registrationFlow: null,
      registrationStage: null,
      accountType: null,
      portalHomeFromRole: '/pos',
      redirectQuery: undefined,
    })
    expect(path).toBe('/pos')
  })

  it('uses home_route_hint for hybrid platform employee', () => {
    const path = resolvePostLoginTarget({
      accountContext: ctx({
        principal_kind: 'platform_employee',
        home_route_hint: '/work-orders',
        guard_hint: 'staff',
        company_id: 9,
      }),
      registrationFlow: null,
      registrationStage: null,
      accountType: null,
      portalHomeFromRole: '/',
      redirectQuery: undefined,
    })
    expect(path).toBe('/work-orders')
  })

  it('falls back to /platform/overview for standalone platform employee without valid hint', () => {
    const path = resolvePostLoginTarget({
      accountContext: ctx({
        principal_kind: 'platform_employee',
        home_route_hint: '/',
        guard_hint: 'platform',
        company_id: null,
      }),
      registrationFlow: null,
      registrationStage: null,
      accountType: null,
      portalHomeFromRole: '/',
      redirectQuery: undefined,
    })
    expect(path).toBe('/platform/overview')
  })
})
