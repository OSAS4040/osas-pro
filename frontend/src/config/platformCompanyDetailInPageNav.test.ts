import { describe, expect, it } from 'vitest'
import {
  platformCompanyDetailInPageNavByTab,
  type PlatformCompanyDetailTabId,
} from '@/config/platformCompanyDetailInPageNav'

const TAB_IDS: PlatformCompanyDetailTabId[] = [
  'overview',
  'finance',
  'customers',
  'vehicles',
  'invoices',
]

describe('platformCompanyDetailInPageNavByTab', () => {
  it('covers every company detail tab', () => {
    expect(Object.keys(platformCompanyDetailInPageNavByTab).sort()).toEqual([...TAB_IDS].sort())
  })

  it('uses HTML-safe anchor id tokens', () => {
    for (const tab of TAB_IDS) {
      for (const { id } of platformCompanyDetailInPageNavByTab[tab]) {
        expect(id).toMatch(/^[a-z][a-z0-9-]*$/i)
      }
    }
  })

  it('uses unique anchor ids per tab', () => {
    for (const tab of TAB_IDS) {
      const items = platformCompanyDetailInPageNavByTab[tab]
      const ids = items.map((i) => i.id)
      expect(new Set(ids).size, tab).toBe(ids.length)
      for (const id of ids) {
        expect(id).toMatch(/^platform-company-/)
      }
    }
  })
})
