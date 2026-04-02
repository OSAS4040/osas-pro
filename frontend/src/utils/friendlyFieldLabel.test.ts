import { afterEach, describe, expect, it, vi } from 'vitest'
import { friendlyFieldLabel } from '@/utils/friendlyFieldLabel'
import { FIELD_LABELS } from '@/utils/validationFieldLabels'

type LocalStorageStub = { getItem: (key: string) => string | null }

function setRuntimeLang(lang: string): void {
  const stub: LocalStorageStub = {
    getItem: (key: string) => (key === 'lang' ? lang : null),
  }
  vi.stubGlobal('localStorage', stub)
}

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('validation field labels map', () => {
  it('keeps critical booking and expiry keys mapped', () => {
    expect(FIELD_LABELS.expires_at).toBeDefined()
    expect(FIELD_LABELS.starts_at).toBeDefined()
    expect(FIELD_LABELS.ends_at).toBeDefined()
  })
})

describe('friendlyFieldLabel', () => {
  it('returns Arabic mapped label when runtime lang is Arabic', () => {
    setRuntimeLang('ar')
    expect(friendlyFieldLabel('expires_at')).toBe('تاريخ الانتهاء')
  })

  it('returns English mapped label when runtime lang is English', () => {
    setRuntimeLang('en')
    expect(friendlyFieldLabel('expires_at')).toBe('Expiration date')
  })

  it('humanizes unknown field names', () => {
    setRuntimeLang('en')
    expect(friendlyFieldLabel('custom_field-name')).toBe('custom field name')
  })
})

