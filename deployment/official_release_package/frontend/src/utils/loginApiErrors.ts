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

function decodeHtmlEntities(input: string): string {
  return input
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
}

export function normalizeApiMessageText(input: string): string {
  let out = input.trim()
  if (out === '') return ''
  out = decodeHtmlEntities(out)

  // Some backends return a JSON-stringified message body.
  if (
    out.length >= 2 &&
    ((out.startsWith('"') && out.endsWith('"')) || (out.startsWith("'") && out.endsWith("'")))
  ) {
    const asDoubleQuoted = out.startsWith("'") ? `"${out.slice(1, -1).replace(/"/g, '\\"')}"` : out
    try {
      const parsed = JSON.parse(asDoubleQuoted)
      if (typeof parsed === 'string' && parsed.trim() !== '') {
        out = parsed.trim()
      }
    } catch {
      // keep raw text when not valid JSON
    }
  }

  return out
    .replace(/\\r\\n/g, '\n')
    .replace(/\\n/g, '\n')
    .replace(/\\t/g, '\t')
    .trim()
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
  const msg = typeof data?.message === 'string' ? normalizeApiMessageText(data.message) : ''
  if (msg !== '') return msg
  return t('login.errBadCredentials')
}
