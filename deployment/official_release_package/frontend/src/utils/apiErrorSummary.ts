import type { AxiosError } from 'axios'
import { localizeBackendMessage, uiByLang } from '@/utils/runtimeLocale'

/** رسائل خادم لا تُعرض للمستخدم النهائي (تُستبدل بنص آمن). */
export function looksLikeTechnicalServerMessage(message: string): boolean {
  const s = String(message).trim()
  if (!s) return false
  if (s.length > 280) return true
  return /SQLSTATE|PDOException|stack trace|syntax error|Illuminate\\\\|Connection refused|ECONNREFUSED|Exception in file|does not exist|Undefined table/i.test(
    s,
  )
}

export function pickFirstValidationMessage(errors: unknown): string {
  if (!errors || typeof errors !== 'object') return ''
  const walk = (node: unknown): string => {
    if (node == null) return ''
    if (typeof node === 'string' && node.trim()) return node.trim()
    if (Array.isArray(node)) {
      for (const x of node) {
        const m = walk(x)
        if (m) return m
      }
      return ''
    }
    if (typeof node === 'object') {
      for (const v of Object.values(node as Record<string, unknown>)) {
        const m = walk(v)
        if (m) return m
      }
    }
    return ''
  }
  return walk(errors)
}

/**
 * نص موحّد للعرض في الشاشة أو toast محلي عند استخدام skipGlobalErrorToast على الطلب.
 */
export function summarizeAxiosError(err: unknown): string {
  const e = err as AxiosError<{ message?: string; errors?: Record<string, string[] | string> }>
  const res = e.response
  if (!res) {
    if (e.code === 'ECONNABORTED' || (typeof e.message === 'string' && /timeout/i.test(e.message))) {
      return uiByLang('انتهت مهلة الطلب. حاول مرة أخرى.', 'The request timed out. Try again.')
    }
    return uiByLang('تعذّر الاتصال بالخادم. تحقق من الشبكة.', 'Could not reach the server. Check your network.')
  }

  const st = res.status
  const d = res.data as { message?: string; errors?: unknown } | undefined

  if (st === 422 && d?.errors) {
    const v = pickFirstValidationMessage(d.errors)
    if (v) return localizeBackendMessage(v)
  }

  const rawMsg = String(d?.message ?? '').trim()
  const msg = rawMsg ? localizeBackendMessage(rawMsg) : ''

  if (st === 403) {
    return msg || uiByLang('لا تملك صلاحية هذا الإجراء.', 'You are not allowed to perform this action.')
  }
  if (st === 404) {
    return msg && !looksLikeTechnicalServerMessage(msg)
      ? msg
      : uiByLang('المورد غير موجود أو لم يعد متاحاً.', 'The resource was not found or is no longer available.')
  }
  if (st === 409) {
    return msg && !looksLikeTechnicalServerMessage(msg)
      ? msg
      : uiByLang(
          'تعارض مع بيانات أحدث (مثلاً تعديل من مستخدم آخر). حدّث الصفحة ثم أعد المحاولة.',
          'Conflict with newer data. Refresh the page and try again.',
        )
  }
  if (st === 402 || st === 423) {
    return msg || uiByLang('لا يمكن المتابعة مع الاشتراك أو وضع القراءة الحالي.', 'Cannot continue with the current subscription or read-only state.')
  }
  if (st === 429) {
    return msg || uiByLang('عدد الطلبات مرتفع. انتظر قليلاً ثم أعد المحاولة.', 'Too many requests. Wait a moment and try again.')
  }
  if (st >= 500) {
    if (msg && !looksLikeTechnicalServerMessage(msg)) return msg
    return uiByLang('حدث خطأ غير متوقع في الخادم.', 'An unexpected server error occurred.')
  }

  if (msg) return msg
  return uiByLang('تعذّر إتمام الطلب.', 'The request could not be completed.')
}
