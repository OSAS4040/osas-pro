import { ref, computed, watch } from 'vue'

export type LangCode = 'ar' | 'en' | 'ur' | 'bn' | 'tl' | 'hi'

export const LANGUAGES: { code: LangCode; label: string; native: string; dir: 'rtl' | 'ltr'; flag: string }[] = [
  { code: 'ar', label: 'Arabic',   native: 'العربية',    dir: 'rtl', flag: '🇸🇦' },
  { code: 'en', label: 'English',  native: 'English',    dir: 'ltr', flag: '🇬🇧' },
  { code: 'ur', label: 'Urdu',     native: 'اردو',       dir: 'rtl', flag: '🇵🇰' },
  { code: 'bn', label: 'Bengali',  native: 'বাংলা',      dir: 'ltr', flag: '🇧🇩' },
  { code: 'tl', label: 'Filipino', native: 'Filipino',   dir: 'ltr', flag: '🇵🇭' },
  { code: 'hi', label: 'Hindi',    native: 'हिंदी',      dir: 'ltr', flag: '🇮🇳' },
]

// ── Load locale JSON files (Vite static imports) ──────────────────────────
import arJson from '@/locales/ar.json'
import enJson from '@/locales/en.json'
import urJson from '@/locales/ur.json'
import bnJson from '@/locales/bn.json'
import tlJson from '@/locales/tl.json'
import hiJson from '@/locales/hi.json'

type LocaleJson = Record<string, any>

const localeData: Record<LangCode, LocaleJson> = {
  ar: arJson,
  en: enJson,
  ur: urJson,
  bn: bnJson,
  tl: tlJson,
  hi: hiJson,
}

// ── Resolve a dot-notation key like "nav.dashboard" or flat key "dashboard" ──
function resolve(data: LocaleJson, key: string): string | undefined {
  // Try dot-notation first (e.g. "nav.dashboard")
  const parts = key.split('.')
  let cur: any = data
  for (const part of parts) {
    if (cur == null || typeof cur !== 'object') return undefined
    cur = cur[part]
  }
  if (typeof cur === 'string') return cur

  // Flat key fallback — search all top-level sections
  for (const section of Object.values(data)) {
    if (section && typeof section === 'object' && !Array.isArray(section)) {
      if (typeof section[key] === 'string') return section[key]
    }
  }
  return undefined
}

// ── Locale state ─────────────────────────────────────────────────────────
export const locale = ref<LangCode>((localStorage.getItem('lang') as LangCode) ?? 'ar')

watch(locale, lang => {
  localStorage.setItem('lang', lang)
  const info = LANGUAGES.find(l => l.code === lang)!
  document.documentElement.setAttribute('dir', info.dir)
  document.documentElement.setAttribute('lang', lang)
})

// Apply on load
const _init = LANGUAGES.find(l => l.code === locale.value)!
document.documentElement.setAttribute('dir', _init.dir)
document.documentElement.setAttribute('lang', _init.code)

// ── Public API ────────────────────────────────────────────────────────────

/** Translate a key using the active locale JSON. Falls back to Arabic then returns key. */
export function t(key: string): string {
  return (
    resolve(localeData[locale.value], key) ??
    resolve(localeData['ar'], key) ??
    key
  )
}

/** Switch the active locale */
export function setLocale(lang: LangCode) {
  locale.value = lang
}

// ── Composable (keeps existing AppLayout API intact) ─────────────────────
export function useLocale() {
  const langInfo = computed(() => LANGUAGES.find(l => l.code === locale.value)!)

  const greetingKey = computed(() => {
    const h = new Date().getHours()
    if (h >= 5 && h < 12) return 'greetings.morning'
    if (h >= 12 && h < 18) return 'greetings.evening'
    if (h >= 18 && h < 24) return 'greetings.evening'
    return 'greetings.night'
  })

  return {
    lang: locale,
    langInfo,
    t,
    greeting: computed(() => t(greetingKey.value)),
    LANGUAGES,
    setLang: (code: LangCode) => { locale.value = code },
    setLocale,
  }
}
