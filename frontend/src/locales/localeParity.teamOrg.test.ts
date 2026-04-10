import { describe, expect, it } from 'vitest'
import ar from './ar.json'
import en from './en.json'
import ur from './ur.json'
import bn from './bn.json'
import tl from './tl.json'
import hi from './hi.json'

type Json = Record<string, unknown>

function stringKeysUnder(obj: Json, prefix: string): string[] {
  const out: string[] = []
  for (const [k, v] of Object.entries(obj)) {
    const p = prefix ? `${prefix}.${k}` : k
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
      out.push(...stringKeysUnder(v as Json, p))
    } else if (typeof v === 'string') {
      out.push(p)
    }
  }
  return out.sort()
}

const SECTIONS = ['teamUsers', 'orgUnits', 'settingsProfile'] as const

function sectionStringKeys(data: Json, section: (typeof SECTIONS)[number]): string[] {
  const sec = data[section]
  if (!sec || typeof sec !== 'object' || Array.isArray(sec)) return []
  return stringKeysUnder(sec as Json, section)
}

describe('locale parity (team / org / settingsProfile)', () => {
  const locales = { ar, en, ur, bn, tl, hi } as const
  const baseline = SECTIONS.flatMap((s) => sectionStringKeys(ar as Json, s))

  it('Arabic baseline has expected sections', () => {
    expect(baseline.length).toBeGreaterThan(30)
    for (const s of SECTIONS) {
      expect(ar).toHaveProperty(s)
    }
  })

  for (const code of Object.keys(locales) as (keyof typeof locales)[]) {
    if (code === 'ar') continue
    it(`${code} string keys match ar for teamUsers, orgUnits, settingsProfile`, () => {
      const keys = SECTIONS.flatMap((s) => sectionStringKeys(locales[code] as Json, s))
      expect(keys).toEqual(baseline)
    })
  }
})
