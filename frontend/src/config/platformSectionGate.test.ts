import { describe, expect, it } from 'vitest'
import {
  PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS,
  PLATFORM_SECTION_ANY_PERMISSIONS,
  canAccessPlatformSection,
  canAccessWithAnyPermission,
} from '@/config/platformSectionGate'

describe('platformSectionGate', () => {
  it('requires any listed permission', () => {
    const has = (k: string) => k === 'platform.ops.read'
    expect(canAccessWithAnyPermission(has, ['platform.ops.read', 'platform.audit.read'])).toBe(true)
    expect(canAccessWithAnyPermission(has, ['platform.audit.read'])).toBe(false)
  })

  it('allows empty key list', () => {
    expect(canAccessWithAnyPermission(() => false, undefined)).toBe(true)
    expect(canAccessWithAnyPermission(() => false, [])).toBe(true)
  })

  it('gates overview slice for pricing-only grant', () => {
    const hasPricingOnly = (k: string) => k === 'platform.pricing.view'
    expect(canAccessPlatformSection(hasPricingOnly, 'overview')).toBe(true)
    expect(canAccessPlatformSection(hasPricingOnly, 'audit')).toBe(false)
  })

  it('defines extra routes for pricing subpaths', () => {
    expect(PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS['platform-pricing-requests']?.length).toBeGreaterThan(0)
    expect(PLATFORM_ROUTE_EXTRA_ANY_PERMISSIONS['platform-company-detail']?.includes('platform.companies.read')).toBe(true)
  })

  it('sections align with backend role slices', () => {
    expect(PLATFORM_SECTION_ANY_PERMISSIONS.ops?.includes('platform.ops.read')).toBe(true)
    expect(PLATFORM_SECTION_ANY_PERMISSIONS['purchase-claims']?.includes('platform.purchase_claims.read')).toBe(true)
  })
})
