import { describe, expect, it } from 'vitest'
import { parseEnabledPortals } from '@/config/portalAccess'

describe('parseEnabledPortals', () => {
  it('enables all optional portals when unset or empty', () => {
    expect(parseEnabledPortals(undefined)).toEqual({
      fleet: true,
      customer: true,
      admin: true,
    })
    expect(parseEnabledPortals('')).toEqual({
      fleet: true,
      customer: true,
      admin: true,
    })
    expect(parseEnabledPortals('   ')).toEqual({
      fleet: true,
      customer: true,
      admin: true,
    })
  })

  it('parses explicit whitelist (case-insensitive)', () => {
    expect(parseEnabledPortals('Fleet,ADMIN')).toEqual({
      fleet: true,
      customer: false,
      admin: true,
    })
    expect(parseEnabledPortals('customer')).toEqual({
      fleet: false,
      customer: true,
      admin: false,
    })
  })
})
