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

describe('usePlatformIntelligenceCandidates', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.removeItem('auth_token')
    vi.resetModules()
  })

  it('does not call API when view_incident_candidates permission missing', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = ['platform.intelligence.signals.read']

    const { usePlatformIntelligenceCandidates } = await import('./usePlatformIntelligenceCandidates')
    const { canLoad, fetchCandidates } = usePlatformIntelligenceCandidates()
    expect(canLoad.value).toBe(false)
    await fetchCandidates()
    expect(apiClient.get).not.toHaveBeenCalled()
  })

  it('loads when platform.intelligence.candidates.read present', async () => {
    const apiClient = (await import('@/lib/apiClient')).default
    vi.mocked(apiClient.get).mockResolvedValue({
      data: {
        data: [
          {
            incident_key: 'ic.test',
            incident_type: 'candidate.single_signal',
            title: 'T',
            summary: 'S',
            why_summary: 'W',
            severity: 'low',
            confidence: 0.55,
            source_signals: ['a'],
            affected_scope: 'x',
            affected_entities: [],
            affected_companies: [1],
            first_seen_at: '2026-01-01T00:00:00Z',
            last_seen_at: '2026-01-01T00:00:00Z',
            recommended_actions: ['راقب'],
            grouping_reason: 'g',
            dedupe_fingerprint: 'f',
          },
        ],
      },
    })

    const auth = useAuthStore()
    auth.user = staffUser() as never
    auth.permissions = ['platform.intelligence.candidates.read']

    const { usePlatformIntelligenceCandidates } = await import('./usePlatformIntelligenceCandidates')
    const { fetchCandidates, candidates, error } = usePlatformIntelligenceCandidates()
    await fetchCandidates()
    expect(apiClient.get).toHaveBeenCalledWith('/platform/intelligence/incident-candidates')
    expect(error.value).toBeNull()
    expect(candidates.value).toHaveLength(1)
    expect(candidates.value[0]?.incident_key).toBe('ic.test')
  })
})
