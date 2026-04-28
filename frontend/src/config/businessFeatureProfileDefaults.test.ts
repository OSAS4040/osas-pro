import { describe, expect, it } from 'vitest'
import { featureMatrixForBusinessType, normalizeBusinessType } from '@/config/businessFeatureProfileDefaults'

describe('featureMatrixForBusinessType', () => {
  it('matches backend policy for retail vs service_center (intelligence, fleet)', () => {
    const retail = featureMatrixForBusinessType('retail')
    const sc = featureMatrixForBusinessType('service_center')
    expect(retail.intelligence).toBe(false)
    expect(sc.intelligence).toBe(true)
    expect(retail.fleet).toBe(false)
    expect(sc.fleet).toBe(true)
    expect(retail.org_structure).toBe(false)
    expect(sc.org_structure).toBe(true)
  })

  it('disables inventory for fleet_operator', () => {
    const fo = featureMatrixForBusinessType('fleet_operator')
    expect(fo.inventory).toBe(false)
    expect(fo.fleet).toBe(true)
  })

  it('keeps fixed_assets off by default for all verticals', () => {
    for (const t of ['service_center', 'retail', 'fleet_operator'] as const) {
      expect(featureMatrixForBusinessType(t).fixed_assets).toBe(false)
    }
  })
})

describe('normalizeBusinessType', () => {
  it('falls back to service_center for unknown', () => {
    expect(normalizeBusinessType(null)).toBe('service_center')
    expect(normalizeBusinessType('')).toBe('service_center')
    expect(normalizeBusinessType('other')).toBe('service_center')
  })

  it('accepts known codes', () => {
    expect(normalizeBusinessType('retail')).toBe('retail')
    expect(normalizeBusinessType('fleet_operator')).toBe('fleet_operator')
  })
})
