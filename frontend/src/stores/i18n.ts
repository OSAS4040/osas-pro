import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import ar from '@/i18n/ar'
import en from '@/i18n/en'
import ur from '@/i18n/ur'
import hi from '@/i18n/hi'
import tl from '@/i18n/tl'
import bn from '@/i18n/bn'

const locales: Record<string, any> = { ar, en, ur, hi, tl, bn }

export const SUPPORTED_LANGUAGES = [
  { code: 'ar', label: 'العربية', flag: '🇸🇦', dir: 'rtl' },
  { code: 'en', label: 'English', flag: '🇺🇸', dir: 'ltr' },
  { code: 'ur', label: 'اردو',   flag: '🇵🇰', dir: 'rtl' },
  { code: 'hi', label: 'हिंदी',  flag: '🇮🇳', dir: 'ltr' },
  { code: 'tl', label: 'Filipino', flag: '🇵🇭', dir: 'ltr' },
  { code: 'bn', label: 'বাংলা',  flag: '🇧🇩', dir: 'ltr' },
]

export const useI18nStore = defineStore('i18n', () => {
  const savedLang = localStorage.getItem('osas_lang') ?? 'ar'
  const currentLang = ref<string>(savedLang)
  const messages = computed(() => locales[currentLang.value] ?? locales['ar'])
  const dir = computed(() => messages.value.dir ?? 'rtl')

  function t(path: string): string {
    const keys = path.split('.')
    let obj: any = messages.value
    for (const k of keys) {
      if (obj == null) return path
      obj = obj[k]
    }
    return typeof obj === 'string' ? obj : path
  }

  function setLang(code: string): void {
    if (!locales[code]) return
    currentLang.value = code
    localStorage.setItem('osas_lang', code)
    document.documentElement.setAttribute('lang', code)
    document.documentElement.setAttribute('dir', locales[code].dir ?? 'rtl')
  }

  watch(currentLang, (code) => {
    document.documentElement.setAttribute('lang', code)
    document.documentElement.setAttribute('dir', locales[code]?.dir ?? 'rtl')
  }, { immediate: true })

  return { currentLang, messages, dir, t, setLang, SUPPORTED_LANGUAGES }
})
