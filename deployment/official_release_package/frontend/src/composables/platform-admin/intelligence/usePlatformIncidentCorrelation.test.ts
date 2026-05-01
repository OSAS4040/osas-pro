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

describe('usePlatformIncidentCorrelation', () => {
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

    const { usePlatformIncidentCorrelation } = await import('./usePlatformIncidentCorrelation')
    const { canView, fetchCorrelation } = usePlatformIncidentCorrelation('inc-1')
    expect(canView.value).toBe(false)
    await fetchCorrelation()
    expect(apiClient.get).not.toHaveBeenCalled()
  })

  it('requests encoded incident key and unwraps data.data', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        data: {
          executive_summary: 'ok',
          causal_signal_links: [],
        },
      },
    })

    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = ['platform.intelligence.incidents.read']

    const { usePlatformIncidentCorrelation } = await import('./usePlatformIncidentCorrelation')
    const { fetchCorrelation, bundle } = usePlatformIncidentCorrelation('a/b')
    await fetchCorrelation()
    expect(apiClient.get).toHaveBeenCalledWith('/platform/intelligence/incidents/a%2Fb/correlation')
    expect(bundle.value?.executive_summary).toBe('ok')
  })
})
