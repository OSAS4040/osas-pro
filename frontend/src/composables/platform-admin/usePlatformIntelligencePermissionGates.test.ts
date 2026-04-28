/** @vitest-environment happy-dom */
import { describe, expect, it, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { usePlatformIntelligencePermissionGates } from './usePlatformIntelligencePermissionGates'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

function minimalStaffUser() {
  return {
    id: 1,
    uuid: '00000000-0000-4000-8000-000000000001',
    name: 'Tester',
    email: 't@test.sa',
    role: 'staff',
    company_id: 1,
    branch_id: 1,
  }
}

describe('usePlatformIntelligencePermissionGates', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.removeItem('auth_token')
  })

  it('enforces platform.* from permission snapshot (no owner bypass)', () => {
    const auth = useAuthStore()
    auth.user = minimalStaffUser() as never
    auth.permissions = [PLATFORM_INTELLIGENCE_PERMISSIONS.signalsRead]

    const { gate } = usePlatformIntelligencePermissionGates()
    expect(gate('view_signals')).toBe(true)
    expect(gate('view_incident_candidates')).toBe(false)
    expect(gate('escalate_incident')).toBe(false)
  })

  it('owner role still cannot escalate without platform grant', () => {
    const auth = useAuthStore()
    auth.user = {
      ...minimalStaffUser(),
      role: 'owner',
    } as never
    auth.permissions = []

    const { gate } = usePlatformIntelligencePermissionGates()
    expect(gate('view_incidents')).toBe(false)
  })
})
