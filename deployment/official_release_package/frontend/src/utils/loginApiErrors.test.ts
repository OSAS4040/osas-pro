import { describe, expect, it } from 'vitest'
import { loginErrorMessageFromPayload, normalizeApiMessageText } from '@/utils/loginApiErrors'

describe('normalizeApiMessageText', () => {
  it('decodes common HTML entities', () => {
    expect(normalizeApiMessageText('&quot;hello&quot; &amp; world')).toBe('"hello" & world')
  })

  it('parses JSON-stringified string bodies', () => {
    expect(normalizeApiMessageText('"خطأ من الخادم"')).toBe('خطأ من الخادم')
  })

  it('converts escaped newlines to real newlines', () => {
    expect(normalizeApiMessageText('line1\\nline2')).toBe('line1\nline2')
  })
})

describe('loginErrorMessageFromPayload', () => {
  const t = (key: string) =>
    key === 'login.apiErrors.account_blocked' ? 'BLOCKED_UI' : key === 'login.apiErrors.invalid_credentials' ? 'BAD_UI' : key

  it('maps message_key to i18n', () => {
    expect(
      loginErrorMessageFromPayload(
        { message_key: 'auth.login.account_blocked', message: 'ignored for logic' },
        t,
      ),
    ).toBe('BLOCKED_UI')
  })

  it('maps invalid_credentials', () => {
    expect(
      loginErrorMessageFromPayload({ message_key: 'auth.login.invalid_credentials', message: 'x' }, t),
    ).toBe('BAD_UI')
  })

  it('falls back to server message when key unknown', () => {
    expect(loginErrorMessageFromPayload({ message: 'Server text' }, t)).toBe('Server text')
  })

  it('normalizes server message fallback (entities / JSON string)', () => {
    // Entities decode to double-quoted text; JSON branch then unwraps the string value.
    expect(loginErrorMessageFromPayload({ message: '&quot;تجربة&quot;' }, t)).toBe('تجربة')
    expect(loginErrorMessageFromPayload({ message: '"نص"' }, t)).toBe('نص')
    expect(loginErrorMessageFromPayload({ message: 'كلمة &amp; رمز' }, t)).toBe('كلمة & رمز')
  })
})
