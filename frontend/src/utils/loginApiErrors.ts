/**
 * Map backend auth payloads (message_key / reason_code) to UI strings.
 * Prefer i18n; use server `message` only as fallback when keys are missing.
 */

const MESSAGE_KEY_TO_I18N: Record<string, string> = {
  'auth.login.invalid_credentials': 'login.apiErrors.invalid_credentials',
  'auth.login.account_inactive': 'login.apiErrors.account_inactive',
  'auth.login.account_suspended': 'login.apiErrors.account_suspended',
  'auth.login.account_blocked': 'login.apiErrors.account_blocked',
  'auth.login.account_disabled': 'login.apiErrors.account_disabled',
  'auth.login.not_allowed': 'login.apiErrors.not_allowed',
  'auth.login.account_not_found': 'login.apiErrors.account_not_found',
}

export function loginErrorMessageFromPayload(
  data: Record<string, unknown> | undefined,
  t: (key: string) => string,
): string {
  const messageKey = typeof data?.message_key === 'string' ? data.message_key : ''
  const i18nKey = messageKey ? MESSAGE_KEY_TO_I18N[messageKey] : ''
  if (i18nKey) {
    const localized = t(i18nKey)
    if (localized && localized !== i18nKey) return localized
  }
  const msg = typeof data?.message === 'string' ? data.message.trim() : ''
  if (msg !== '') return msg
  return t('login.errBadCredentials')
}
