/** @vitest-environment happy-dom */
import { describe, expect, it, beforeEach, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/lib/apiClient', () => ({
  default: {
    get: vi.fn(),
  },
}))

function staffUser() {
  return {
    id: 1,
    uuid: '00000000-0000-4000-8000-000000000001',
    name: 'T',
    email: 't@test.sa',
    role: 'staff',
    company_id: 1,
    branch_id: 1,
  }
}

describe('usePlatformCommandSurface', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.removeItem('auth_token')
    vi.resetModules()
  })

  it('does not call API when incidents.read missing', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = []

    const { usePlatformCommandSurface } = await import('./usePlatformCommandSurface')
    const { canView, fetchSurface } = usePlatformCommandSurface()
    expect(canView.value).toBe(false)
    await fetchSurface()
    expect(apiClient.get).not.toHaveBeenCalled()
  })

  it('loads payload when platform.intelligence.incidents.read present', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        summary: { open_high_severity: 0 },
        open_high_severity_incidents: [],
        recently_escalated_incidents: [],
        monitoring_incidents_sample: [],
        decisions_requiring_follow_up: [],
        recent_workflow_executions: [],
        signals_not_on_open_incidents: [],
        candidates_likely_to_materialize: [],
        companies_with_stacked_signals: [],
        meta: {},
      },
    })

    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = ['platform.intelligence.incidents.read']

    const { usePlatformCommandSurface } = await import('./usePlatformCommandSurface')
    const { fetchSurface, payload } = usePlatformCommandSurface()
    await fetchSurface()
    expect(apiClient.get).toHaveBeenCalledWith('/platform/intelligence/command-surface')
    expect(payload.value?.summary).toEqual({ open_high_severity: 0 })
  })
})
