import { describe, expect, it } from 'vitest'
import {
  invoicePrintCompanyDisplayName,
  invoicePrintLogoMonogram,
  isLegacyPlatformBrandCompany,
} from './invoicePrintDisplay'

describe('invoicePrintDisplay', () => {
  it('detects legacy demo company', () => {
    expect(isLegacyPlatformBrandCompany({ name: 'OSAS Platform' })).toBe(true)
    expect(isLegacyPlatformBrandCompany({ name: 'Asas Platform' })).toBe(true)
    expect(isLegacyPlatformBrandCompany({ name_ar: 'منصة أواس' })).toBe(true)
    expect(isLegacyPlatformBrandCompany({ name_ar: 'أسس' })).toBe(true)
    expect(isLegacyPlatformBrandCompany({ name: 'My Garage', name_ar: 'ورشتي' })).toBe(false)
  })

  it('maps legacy names to أسس برو / Osas Pro', () => {
    const c = { name: 'OSAS Platform', name_ar: 'منصة أواس' }
    expect(invoicePrintCompanyDisplayName(c, 'ar')).toBe('أسس برو')
    expect(invoicePrintCompanyDisplayName(c, 'en')).toBe('Osas Pro')
  })

  it('logo monogram uses OP for legacy', () => {
    expect(invoicePrintLogoMonogram({ name_ar: 'منصة أواس' })).toBe('OP')
    expect(invoicePrintLogoMonogram({ name: 'Acme Corp' })).toBe('AC')
  })
})
