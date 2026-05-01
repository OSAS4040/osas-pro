/** @vitest-environment node */
import { describe, expect, it } from 'vitest'
import { datetimeLocalToIso } from '@/utils/datetimeLocalToIso'

describe('datetimeLocalToIso', () => {
  it('returns null for empty or invalid', () => {
    expect(datetimeLocalToIso('')).toBeNull()
    expect(datetimeLocalToIso('   ')).toBeNull()
    expect(datetimeLocalToIso('not-a-date')).toBeNull()
  })

  it('parses datetime-local shape to ISO string', () => {
    const iso = datetimeLocalToIso('2026-06-15T14:30')
    expect(iso).toMatch(/^\d{4}-\d{2}-\d{2}T/)
    expect(iso).not.toContain('Invalid')
  })
})
