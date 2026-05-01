import { describe, expect, it } from 'vitest'
import {
  platformAdminNavItems,
  platformAdminSidebarGroups,
  platformPathFromAdminHash,
} from '@/config/platformAdminNav'

describe('platformPathFromAdminHash (legacy /admin#admin-section-*)', () => {
  const cases: [string, string][] = [
    ['#admin-section-overview', '/platform/overview'],
    ['#admin-section-governance', '/platform/governance'],
    ['#admin-section-ops', '/platform/ops'],
    ['#admin-section-tenants', '/platform/companies'],
    ['#admin-section-customers', '/platform/customers'],
    ['#admin-section-plans', '/platform/plans'],
    ['#admin-section-operator-commands', '/platform/operator-commands'],
    ['#admin-section-audit', '/platform/audit'],
    ['#admin-section-finance', '/platform/finance'],
    ['#admin-section-cancellations', '/platform/cancellations'],
    ['#admin-section-banner', '/platform/announcements'],
  ]

  it.each(cases)('%s → %s', (hash, path) => {
    expect(platformPathFromAdminHash(hash)).toBe(path)
  })

  it('normalizes hash without leading #', () => {
    expect(platformPathFromAdminHash('admin-section-audit')).toBe('/platform/audit')
  })

  it('unknown hash falls back to overview (no redirect loop target)', () => {
    expect(platformPathFromAdminHash('#admin-section-unknown')).toBe('/platform/overview')
    expect(platformPathFromAdminHash('')).toBe('/platform/overview')
  })
})

describe('platformAdminNavItems', () => {
  it('lists every platform child route used in Phase 4 gate', () => {
    const names = platformAdminNavItems.map((i) => i.routeName).sort()
    expect(names).toEqual(
      [
        'platform-announcements',
        'platform-audit',
        'platform-cancellations',
        'platform-companies',
        'platform-customers',
        'platform-finance',
        'platform-governance',
        'platform-incidents',
        'platform-intelligence-command',
        'platform-notifications',
        'platform-operator-commands',
        'platform-ops',
        'platform-overview',
        'platform-plans',
        'platform-support',
      ].sort(),
    )
  })

  it('has unique route names and section ids', () => {
    const routeNames = new Set(platformAdminNavItems.map((i) => i.routeName))
    const ids = new Set(platformAdminNavItems.map((i) => i.id))
    expect(routeNames.size).toBe(platformAdminNavItems.length)
    expect(ids.size).toBe(platformAdminNavItems.length)
  })

  it('sidebar groups partition every nav section id exactly once', () => {
    const fromGroups = platformAdminSidebarGroups.flatMap((g) => g.sectionIds)
    const sortedNavIds = platformAdminNavItems.map((i) => i.id).sort()
    const sortedGroupIds = [...fromGroups].sort()
    expect(fromGroups.length).toBe(platformAdminNavItems.length)
    expect(sortedGroupIds).toEqual(sortedNavIds)
    expect(new Set(fromGroups).size).toBe(fromGroups.length)
  })

  it('includes sidebar card labels and hints for unified platform nav', () => {
    for (const item of platformAdminNavItems) {
      expect(item.navEyebrow.trim().length).toBeGreaterThan(0)
      expect(item.navHint.trim().length).toBeGreaterThan(0)
    }
  })
})
