import { uiByLang } from '@/utils/runtimeLocale'
import { FIELD_LABELS } from '@/utils/validationFieldLabels'

export function friendlyFieldLabel(rawField: string): string {
  const key = String(rawField || '').trim()
  if (!key) return ''

  const mapped = FIELD_LABELS[key]
  if (mapped) return uiByLang(mapped.ar, mapped.en)

  return key.replace(/[_-]+/g, ' ')
}

