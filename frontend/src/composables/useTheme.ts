import { ref, watch } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

/** مرّة واحدة بعد تحديث الهوية البنفسجية — يُحدَّث عند تغيير ترحيل الألوان مستقبلاً */
const THEME_BRAND_REVISION = '2026-04-osas-purple'
const THEME_BRAND_REVISION_KEY = 'theme_brand_revision'

/** ألوان الهوية القديمة (أزرق/تركواز) → بنفسجي أسس الحالي — يطبَّق على localStorage وبيانات الشركة المحمّلة */
const LEGACY_PRIMARY_TO_BRAND: Record<string, string> = {
  '#2563eb': '#7c3aed',
  '#1d4ed8': '#6d28d9',
  '#3b82f6': '#8b5cf6',
  '#0d9488': '#7c3aed',
  '#14b8a6': '#8b5cf6',
  '#0f766e': '#6d28d9',
  '#115e59': '#5b21b6',
  '#134e4a': '#5b21b6',
  '#10b981': '#8b5cf6',
  '#059669': '#7c3aed',
  '#047857': '#6d28d9',
  '#065f46': '#5b21b6',
  '#34d399': '#a78bfa',
  '#2dd4bf': '#8b5cf6',
  '#5eead4': '#a78bfa',
}

function normHex(hex: string): string {
  return hex.trim().toLowerCase()
}

export function mapLegacyBrandPrimary(hex: string | undefined | null): string {
  if (!hex || typeof hex !== 'string') return '#7c3aed'
  const raw = hex.trim()
  const key = normHex(raw.startsWith('#') ? raw : `#${raw}`)
  return LEGACY_PRIMARY_TO_BRAND[key] ?? key
}

function migrateStoredThemeBrandOnce(): void {
  if (typeof localStorage === 'undefined') return
  try {
    if (localStorage.getItem(THEME_BRAND_REVISION_KEY) === THEME_BRAND_REVISION) return

    const preset = localStorage.getItem('theme_preset')
    const colorRaw = localStorage.getItem('theme_color')
    const colorNorm = colorRaw ? normHex(colorRaw) : ''

    const oldDefaultBlue =
      preset === 'enterprise-blue' && (!colorRaw || colorNorm === '#2563eb')

    const violetPresetButBlueHex =
      preset === 'violet-executive' && colorNorm === '#2563eb'

    const legacyHexOnly = colorRaw && LEGACY_PRIMARY_TO_BRAND[colorNorm]

    if (oldDefaultBlue || violetPresetButBlueHex || legacyHexOnly) {
      localStorage.setItem('theme_preset', 'violet-executive')
      localStorage.setItem('theme_color', mapLegacyBrandPrimary(colorRaw || '#2563eb'))
    }

    localStorage.setItem(THEME_BRAND_REVISION_KEY, THEME_BRAND_REVISION)
  } catch {
    /* private mode / quota */
  }
}

migrateStoredThemeBrandOnce()

type ThemePreset = {
  name: string
  label: string
  primary: string
  hover: string
  ring: string
  css: string
}

export const THEME_PRESETS: ThemePreset[] = [
  { name: 'violet-executive', label: 'بنفسجي Osas (افتراضي)', primary: '#7c3aed', hover: '#6d28d9', ring: '#8b5cf6', css: '' },
  { name: 'enterprise-blue', label: 'Enterprise Blue', primary: '#2563eb', hover: '#1d4ed8', ring: '#3b82f6', css: '' },
  { name: 'slate-professional', label: 'Slate Professional', primary: '#475569', hover: '#334155', ring: '#64748b', css: '' },
  { name: 'rose', label: 'وردي', primary: '#e11d48', hover: '#be123c', ring: '#f43f5e', css: '' },
  { name: 'amber', label: 'ذهبي', primary: '#d97706', hover: '#b45309', ring: '#f59e0b', css: '' },
]

export function hexToRgb(hex: string): string {
  const r = parseInt(hex.slice(1,3), 16)
  const g = parseInt(hex.slice(3,5), 16)
  const b = parseInt(hex.slice(5,7), 16)
  return `${r} ${g} ${b}`
}

/** Legacy: teal preset أصبح بنفسجي؛ الاسم القديم violet يُوجَّه للبنفسجي الحالي. */
export function normalizeThemePresetName(name: string): string {
  if (name === 'violet' || name === 'emerald-executive') return 'violet-executive'
  return name
}

function applyTheme(primaryHex: string) {
  const normalized = mapLegacyBrandPrimary(primaryHex)
  const root = document.documentElement
  root.style.setProperty('--color-primary', normalized)

  // Generate scale from base color
  const presetColors: Record<string, string[]> = {
    '#2563eb': ['#eff6ff','#dbeafe','#bfdbfe','#93c5fd','#60a5fa','#3b82f6','#2563eb','#1d4ed8','#1e40af','#1e3a8a'],
    '#0d9488': ['#f5f3ff','#ede9fe','#ddd6fe','#c4b5fd','#a78bfa','#8b5cf6','#7c3aed','#6d28d9','#5b21b6','#4c1d95'],
    '#7c3aed': ['#faf8ff','#f3edff','#e9e1fc','#d4c5f9','#c4b5fd','#a78bfa','#8b5cf6','#7c3aed','#6d28d9','#5b21b6'],
    '#e11d48': ['#fff1f2','#ffe4e6','#fecdd3','#fda4af','#fb7185','#f43f5e','#e11d48','#be123c','#9f1239','#881337'],
    '#d97706': ['#fffbeb','#fef3c7','#fde68a','#fcd34d','#fbbf24','#f59e0b','#d97706','#b45309','#92400e','#78350f'],
    '#475569': ['#f8fafc','#f1f5f9','#e2e8f0','#cbd5e1','#94a3b8','#64748b','#475569','#334155','#1e293b','#0f172a'],
  }

  const scale = presetColors[normalized] ?? presetColors['#7c3aed']
  const shades = [50,100,200,300,400,500,600,700,800,900]

  shades.forEach((shade, i) => {
    root.style.setProperty(`--color-primary-${shade}`, scale[i])
  })

  // Update Tailwind CSS variables
  const styleId = 'dynamic-theme'
  let el = document.getElementById(styleId)
  if (!el) { el = document.createElement('style'); el.id = styleId; document.head.appendChild(el) }

  el.textContent = `
    :root { --primary: ${normalized}; }
    .text-primary-600 { color: ${scale[6]} !important; }
    .text-primary-700 { color: ${scale[7]} !important; }
    .text-primary-400 { color: ${scale[4]} !important; }
    .bg-primary-600 { background-color: ${scale[6]} !important; }
    .bg-primary-700 { background-color: ${scale[7]} !important; }
    .hover\\:bg-primary-700:hover { background-color: ${scale[7]} !important; }
    .bg-primary-50 { background-color: ${scale[0]} !important; }
    .bg-primary-100 { background-color: ${scale[1]} !important; }
    .border-primary-500 { border-color: ${scale[5]} !important; }
    .border-primary-400 { border-color: ${scale[4]} !important; }
    .focus\\:ring-primary-500:focus { --tw-ring-color: ${scale[5]} !important; }
    .text-primary-500 { color: ${scale[5]} !important; }
    .text-primary-300 { color: ${scale[3]} !important; }
    .dark .text-primary-400 { color: ${scale[4]} !important; }
    .dark .bg-primary-900\\/20 { background-color: ${scale[9]}33 !important; }
    .dark .bg-primary-900\\/40 { background-color: ${scale[9]}66 !important; }
  `
}

const savedPresetRaw = localStorage.getItem('theme_preset') ?? 'violet-executive'
const savedPreset = normalizeThemePresetName(savedPresetRaw)
if (savedPreset !== savedPresetRaw) {
  localStorage.setItem('theme_preset', savedPreset)
}

let initialThemeColor = mapLegacyBrandPrimary(localStorage.getItem('theme_color') ?? '#7c3aed')
if (savedPresetRaw === 'violet') {
  initialThemeColor = '#7c3aed'
  localStorage.setItem('theme_color', initialThemeColor)
}
const LEGACY_TEALS = ['#0d9488', '#14b8a6', '#115e59', '#134e4a']
if (LEGACY_TEALS.includes(initialThemeColor)) {
  initialThemeColor = '#7c3aed'
  localStorage.setItem('theme_color', initialThemeColor)
}

const currentTheme = ref<string>(initialThemeColor)
const currentPreset = ref<string>(savedPreset)
const themeLoadedFromCompany = ref(false)

watch(currentTheme, (c) => {
  localStorage.setItem('theme_color', c)
  applyTheme(c)
})
watch(currentPreset, (p) => localStorage.setItem('theme_preset', p))

// Apply on load
applyTheme(currentTheme.value)

export function useTheme() {
  function setThemePreset(name: string) {
    const preset = THEME_PRESETS.find((p) => p.name === normalizeThemePresetName(name))
    if (!preset) return
    currentPreset.value = preset.name
    currentTheme.value = preset.primary
  }

  function setTheme(hexColor: string) {
    currentPreset.value = 'custom'
    currentTheme.value = hexColor
  }

  async function loadCompanyTheme(): Promise<void> {
    if (themeLoadedFromCompany.value) return
    const auth = useAuthStore()
    if (!auth.user?.company_id) return
    try {
      const { data } = await apiClient.get(`/companies/${auth.user.company_id}/settings`)
      const uiTheme = data?.data?.ui_theme ?? null
      if (uiTheme?.preset && typeof uiTheme.preset === 'string') {
        setThemePreset(normalizeThemePresetName(uiTheme.preset))
      }
      if (uiTheme?.primary && typeof uiTheme.primary === 'string') {
        currentTheme.value = mapLegacyBrandPrimary(uiTheme.primary)
      }
      themeLoadedFromCompany.value = true
    } catch {
      // Keep local fallback
    }
  }

  async function saveCompanyTheme(): Promise<void> {
    const auth = useAuthStore()
    if (!auth.user?.company_id) return
    await apiClient.patch(`/companies/${auth.user.company_id}/settings`, {
      ui_theme: {
        preset: currentPreset.value,
        primary: currentTheme.value,
      },
    })
  }

  return {
    currentTheme,
    currentPreset,
    presets: THEME_PRESETS,
    setTheme,
    setThemePreset,
    loadCompanyTheme,
    saveCompanyTheme,
  }
}
