import { tryTranslateLaravelMessage } from '@/utils/laravelValidationAr'

export type RuntimeLang = 'ar' | 'en' | 'ur' | 'bn' | 'tl' | 'hi'

export function getRuntimeLang(): RuntimeLang {
  const raw = (localStorage.getItem('lang') || 'ar').toLowerCase()
  const allowed: RuntimeLang[] = ['ar', 'en', 'ur', 'bn', 'tl', 'hi']
  return (allowed.includes(raw as RuntimeLang) ? raw : 'ar') as RuntimeLang
}

export function isArabicRuntime(): boolean {
  return getRuntimeLang() === 'ar'
}

export function uiByLang(ar: string, en: string): string {
  return isArabicRuntime() ? ar : en
}

/**
 * Localize backend/plain messages for runtime UI.
 * Keeps original when no known pattern is matched.
 */
export function localizeBackendMessage(message: unknown): string {
  const raw = String(message ?? '').trim()
  if (!raw) return ''

  if (isArabicRuntime()) {
    const generic = tryTranslateLaravelMessage(raw, true)
    if (generic) return generic
    if (/the event factor now is older/i.test(raw)) {
      return 'وقت الحدث أقدم من الوقت الحالي. يرجى اختيار وقت حالي أو مستقبلي.'
    }
    const stock = raw.match(
      /Insufficient stock for product\s*#?(\d+)\.?\s*Available:?\s*([0-9.]+)\s*,?\s*Requested:?\s*([0-9.]+)/i,
    )
    if (stock) {
      const [, productId, available, requested] = stock
      return `المخزون غير كافٍ للصنف رقم ${productId}. المتاح: ${available}، المطلوب: ${requested}.`
    }

    if (/the given data was invalid/i.test(raw)) return 'البيانات المدخلة غير صحيحة.'
    // Common scheduling/booking validation messages from backend validators.
    if (/event.*(older|past|until now|after now|future)/i.test(raw)) {
      return 'وقت الموعد غير صالح. يرجى اختيار وقت حالي أو مستقبلي.'
    }
    if (/(must|should).*(be|is).*(after|greater than).*(now|current)/i.test(raw)) {
      return 'يجب أن يكون الوقت بعد الوقت الحالي.'
    }
    if (/(date|time).*(must|should).*(after|later than).*(today|now)/i.test(raw)) {
      return 'التاريخ/الوقت يجب أن يكون لاحقًا للوقت الحالي.'
    }
    if (/unauthenticated/i.test(raw)) return 'انتهت الجلسة. الرجاء تسجيل الدخول مرة أخرى.'
    if (/forbidden/i.test(raw)) return 'ليس لديك صلاحية لتنفيذ هذا الإجراء.'
    if (/too many requests/i.test(raw)) return 'عدد الطلبات كبير جدًا. حاول مرة أخرى بعد قليل.'
    if (/server error|internal server error/i.test(raw)) return 'حدث خطأ داخلي في الخادم.'
  }

  return raw
}

export function localizeApiErrorPayload(data: any): any {
  if (!data || typeof data !== 'object') return data

  const next = { ...data }
  if (typeof next.message !== 'undefined') {
    next.message = localizeBackendMessage(next.message)
  }

  if (next.errors && typeof next.errors === 'object') {
    const mapped: Record<string, any> = {}
    for (const [k, v] of Object.entries(next.errors as Record<string, any>)) {
      if (Array.isArray(v)) {
        mapped[k] = v.map((x) => localizeBackendMessage(x))
      } else {
        mapped[k] = localizeBackendMessage(v)
      }
    }
    next.errors = mapped
  }

  return next
}
