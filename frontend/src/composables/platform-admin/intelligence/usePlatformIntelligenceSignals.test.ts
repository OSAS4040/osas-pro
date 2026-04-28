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

describe('usePlatformIntelligenceSignals', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.removeItem('auth_token')
    vi.resetModules()
  })

  it('does not call API when view_signals permission missing', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = []

    const { usePlatformIntelligenceSignals } = await import('./usePlatformIntelligenceSignals')
    const { canLoad, fetchSignals } = usePlatformIntelligenceSignals()
    expect(canLoad.value).toBe(false)
    await fetchSignals()
    expect(apiClient.get).not.toHaveBeenCalled()
  })

  it('loads when platform.intelligence.signals.read present', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        data: [
          {
            signal_key: 'sig.test',
            signal_type: 'rule',
            title: 'T',
            summary: 'S',
            why_summary: 'W',
            severity: 'low',
            confidence: 0.5,
            source: 'operations',
            source_ref: null,
            affected_scope: 'platform:test',
            affected_entities: [],
            affected_companies: [],
            first_seen_at: '2026-01-01T00:00:00Z',
            last_seen_at: '2026-01-01T00:00:00Z',
            recommended_next_step: 'راقب',
            correlation_keys: [],
            trace_id: null,
            correlation_id: null,
          },
        ],
      },
    })

    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = ['platform.intelligence.signals.read']

    const { usePlatformIntelligenceSignals } = await import('./usePlatformIntelligenceSignals')
    const { fetchSignals, signals, error } = usePlatformIntelligenceSignals()
    await fetchSignals()
    expect(apiClient.get).toHaveBeenCalledWith('/platform/intelligence/signals')
    expect(error.value).toBeNull()
    expect(signals.value).toHaveLength(1)
    expect(signals.value[0]?.signal_key).toBe('sig.test')
  })
})
