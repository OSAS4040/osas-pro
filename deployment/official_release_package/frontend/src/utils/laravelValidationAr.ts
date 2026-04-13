import { FIELD_LABELS } from '@/utils/validationFieldLabels'

/**
 * ترجمة رسائل Laravel الافتراضية (إنجليزي) عندما يعيد الـ API message = أول خطأ.
 */
export function tryTranslateLaravelMessage(raw: string, isArabic: boolean): string | null {
  const s = String(raw ?? '').trim()
  if (!s || !isArabic) return null

  const req = /^The\s+(.+?)\s+field\s+is\s+required\.?$/i.exec(s)
  if (req) {
    const path = req[1].trim()
    const segments = path.split('.').filter(Boolean)
    const last = segments[segments.length - 1] ?? path
    const first = segments[0] ?? path
    const mapped = FIELD_LABELS[last]?.ar ?? FIELD_LABELS[first]?.ar
    const label = mapped ?? last.replace(/_/g, ' ')
    return `حقل «${label}» مطلوب.`
  }

  if (/^validation failed\.?$/i.test(s)) {
    return 'البيانات المدخلة غير صحيحة.'
  }

  return null
}
