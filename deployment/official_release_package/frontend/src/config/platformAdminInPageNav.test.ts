import { describe, expect, it } from 'vitest'
import { platformInPageNavBySection } from '@/config/platformAdminInPageNav'
import { platformAdminNavItems } from '@/config/platformAdminNav'

describe('platformInPageNavBySection', () => {
  it('defines an array for every platform nav section (same keys as sidebar)', () => {
    const navIds = platformAdminNavItems.map((i) => i.id).sort()
    const inPageKeys = Object.keys(platformInPageNavBySection).sort()
    expect(inPageKeys).toEqual(navIds)
  })

  it('uses HTML-safe anchor id tokens (letters, digits, hyphen)', () => {
    for (const items of Object.values(platformInPageNavBySection)) {
      for (const { id } of items) {
        expect(id).toMatch(/^[a-z][a-z0-9-]*$/i)
      }
    }
  })

  it('uses unique anchor ids within each non-empty section', () => {
    for (const [section, items] of Object.entries(platformInPageNavBySection)) {
      if (items.length === 0) continue
      const ids = items.map((i) => i.id)
      const unique = new Set(ids)
      expect(unique.size, `duplicate ids in section ${section}`).toBe(ids.length)
      for (const id of ids) {
        expect(id.trim(), `empty id in ${section}`).not.toBe('')
        expect(id, `expected stable prefix in ${section}`).toMatch(/^platform-/)
      }
    }
  })
})
