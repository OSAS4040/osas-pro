import { describe, expect, it } from 'vitest'
import {
  canAccessStaffBusinessIntelligence,
  canAccessStaffCommandCenter,
  canAccessStaffOperationsArea,
  canAccessWorkshopArea,
  tenantSectionOpen,
} from '@/config/staffFeatureGate'

function matrix(enabled: Record<string, boolean>) {
  return (key: string) => enabled[key] !== false
}

describe('tenantSectionOpen', () => {
  it('returns true for owner regardless of matrix', () => {
    expect(tenantSectionOpen(true, () => false, 'operations')).toBe(true)
    expect(tenantSectionOpen(true, () => false, 'hr')).toBe(true)
  })

  it('delegates to isEnabled for non-owner', () => {
    expect(tenantSectionOpen(false, matrix({ operations: true }), 'operations')).toBe(true)
    expect(tenantSectionOpen(false, matrix({ operations: false }), 'operations')).toBe(false)
  })
})

describe('canAccessStaffOperationsArea', () => {
  it('matches tenantSectionOpen for operations key', () => {
    expect(canAccessStaffOperationsArea(true, matrix({ operations: false }))).toBe(true)
    expect(canAccessStaffOperationsArea(false, matrix({ operations: true }))).toBe(true)
    expect(canAccessStaffOperationsArea(false, matrix({ operations: false }))).toBe(false)
  })
})

describe('canAccessWorkshopArea', () => {
  it('uses hr gate', () => {
    expect(canAccessWorkshopArea(false, matrix({ hr: true }))).toBe(true)
    expect(canAccessWorkshopArea(false, matrix({ hr: false }))).toBe(false)
  })
})

describe('canAccessStaffBusinessIntelligence', () => {
  it('requires build flag and intelligence section', () => {
    expect(
      canAccessStaffBusinessIntelligence({
        buildFlagOn: false,
        isOwner: true,
        isEnabled: () => true,
      }),
    ).toBe(false)
    expect(
      canAccessStaffBusinessIntelligence({
        buildFlagOn: true,
        isOwner: false,
        isEnabled: matrix({ intelligence: true }),
      }),
    ).toBe(true)
    expect(
      canAccessStaffBusinessIntelligence({
        buildFlagOn: true,
        isOwner: false,
        isEnabled: matrix({ intelligence: false }),
      }),
    ).toBe(false)
  })
})

describe('canAccessStaffCommandCenter', () => {
  it('requires BI access plus intelligence report permission', () => {
    expect(
      canAccessStaffCommandCenter({
        buildFlagOn: true,
        isOwner: true,
        isEnabled: matrix({ intelligence: true }),
        hasIntelligenceReportPermission: false,
      }),
    ).toBe(false)
    expect(
      canAccessStaffCommandCenter({
        buildFlagOn: true,
        isOwner: true,
        isEnabled: matrix({ intelligence: true }),
        hasIntelligenceReportPermission: true,
      }),
    ).toBe(true)
  })
})
