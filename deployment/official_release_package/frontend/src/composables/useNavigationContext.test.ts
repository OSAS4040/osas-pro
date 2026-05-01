import { describe, expect, it, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'
import { useNavigationContext } from '@/composables/useNavigationContext'

function stubLocalStorage(): void {
  const store: Record<string, string> = {}
  globalThis.localStorage = {
    getItem: (k: string) => (k in store ? store[k] : null),
    setItem: (k: string, v: string) => {
      store[k] = v
    },
    removeItem: (k: string) => {
      delete store[k]
    },
    clear: () => {
      for (const k of Object.keys(store)) delete store[k]
    },
    get length() {
      return Object.keys(store).length
    },
    key: (i: number) => Object.keys(store)[i] ?? null,
  } as Storage
}

describe('useNavigationContext', () => {
  beforeEach(() => {
    stubLocalStorage()
    setActivePinia(createPinia())
  })

  it('staffShellReady is false until session and business profile load for tenant staff', () => {
    const auth = useAuthStore()
    const biz = useBusinessProfileStore()
    auth.token = 't'
    auth.sessionResolved = true
    auth.user = {
      id: 1,
      uuid: 'u',
      name: 'Staff',
      email: 's@test.sa',
      role: 'staff',
      company_id: 5,
      branch_id: 1,
    }
    auth.permissions = []
    biz.loaded = false

    const { staffShellReady } = useNavigationContext()
    expect(staffShellReady.value).toBe(false)

    biz.loaded = true
    expect(staffShellReady.value).toBe(true)
  })

  it('staffShellReady is true for platform user without company without waiting on biz', () => {
    const auth = useAuthStore()
    const biz = useBusinessProfileStore()
    auth.token = 't'
    auth.sessionResolved = true
    auth.user = {
      id: 1,
      uuid: 'u',
      name: 'Ops',
      email: 'ops@p.sa',
      role: 'owner',
      company_id: null,
      branch_id: null,
      is_platform_user: true,
    }
    auth.accountContext = {
      principal_kind: 'platform_employee',
      user_id: 1,
      company_id: null,
      customer_id: null,
      home_route_hint: '/platform/overview',
      guard_hint: 'platform',
      role: 'owner',
      requires_context_selection: false,
      display_context: {},
      platform_role: 'super_admin',
    }
    biz.loaded = false

    const { staffShellReady } = useNavigationContext()
    expect(staffShellReady.value).toBe(true)
  })

  it('resolvedPortal reads guard_hint platform', () => {
    const auth = useAuthStore()
    auth.sessionResolved = true
    auth.user = {
      id: 1,
      uuid: 'u',
      name: 'Ops',
      email: 'ops@p.sa',
      role: 'owner',
      company_id: null,
      branch_id: null,
    }
    auth.accountContext = {
      principal_kind: 'platform_employee',
      user_id: 1,
      company_id: null,
      customer_id: null,
      home_route_hint: '/platform/overview',
      guard_hint: 'platform',
      role: 'owner',
      requires_context_selection: false,
      display_context: {},
    }

    const { resolvedPortal } = useNavigationContext()
    expect(resolvedPortal.value).toBe('platform')
  })
})
