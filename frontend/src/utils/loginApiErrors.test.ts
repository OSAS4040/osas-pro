import { describe, expect, it } from 'vitest'
import { loginErrorMessageFromPayload } from '@/utils/loginApiErrors'

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
})
