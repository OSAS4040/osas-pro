import { ref, watch } from 'vue'

type ThemePreset = {
  name: string
  label: string
  primary: string
  hover: string
  ring: string
  css: string
}

export const THEME_PRESETS: ThemePreset[] = [
  { name: 'blue',   label: 'أزرق (افتراضي)',  primary: '#2563eb', hover: '#1d4ed8', ring: '#3b82f6', css: '' },
  { name: 'teal',   label: 'فيروزي',          primary: '#0d9488', hover: '#0f766e', ring: '#14b8a6', css: '' },
  { name: 'violet', label: 'بنفسجي',          primary: '#7c3aed', hover: '#6d28d9', ring: '#8b5cf6', css: '' },
  { name: 'rose',   label: 'وردي',            primary: '#e11d48', hover: '#be123c', ring: '#f43f5e', css: '' },
  { name: 'amber',  label: 'ذهبي',            primary: '#d97706', hover: '#b45309', ring: '#f59e0b', css: '' },
  { name: 'slate',  label: 'رمادي',           primary: '#475569', hover: '#334155', ring: '#64748b', css: '' },
]

export function hexToRgb(hex: string): string {
  const r = parseInt(hex.slice(1,3), 16)
  const g = parseInt(hex.slice(3,5), 16)
  const b = parseInt(hex.slice(5,7), 16)
  return `${r} ${g} ${b}`
}

function applyTheme(primaryHex: string) {
  const root = document.documentElement
  root.style.setProperty('--color-primary', primaryHex)

  // Generate scale from base color
  const presetColors: Record<string, string[]> = {
    '#2563eb': ['#eff6ff','#dbeafe','#bfdbfe','#93c5fd','#60a5fa','#3b82f6','#2563eb','#1d4ed8','#1e40af','#1e3a8a'],
    '#0d9488': ['#f0fdfa','#ccfbf1','#99f6e4','#5eead4','#2dd4bf','#14b8a6','#0d9488','#0f766e','#115e59','#134e4a'],
    '#7c3aed': ['#f5f3ff','#ede9fe','#ddd6fe','#c4b5fd','#a78bfa','#8b5cf6','#7c3aed','#6d28d9','#5b21b6','#4c1d95'],
    '#e11d48': ['#fff1f2','#ffe4e6','#fecdd3','#fda4af','#fb7185','#f43f5e','#e11d48','#be123c','#9f1239','#881337'],
    '#d97706': ['#fffbeb','#fef3c7','#fde68a','#fcd34d','#fbbf24','#f59e0b','#d97706','#b45309','#92400e','#78350f'],
    '#475569': ['#f8fafc','#f1f5f9','#e2e8f0','#cbd5e1','#94a3b8','#64748b','#475569','#334155','#1e293b','#0f172a'],
  }

  const scale = presetColors[primaryHex] ?? presetColors['#2563eb']
  const shades = [50,100,200,300,400,500,600,700,800,900]

  shades.forEach((shade, i) => {
    root.style.setProperty(`--color-primary-${shade}`, scale[i])
  })

  // Update Tailwind CSS variables
  const styleId = 'dynamic-theme'
  let el = document.getElementById(styleId)
  if (!el) { el = document.createElement('style'); el.id = styleId; document.head.appendChild(el) }

  el.textContent = `
    :root { --primary: ${primaryHex}; }
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

const currentTheme = ref<string>(localStorage.getItem('theme_color') ?? '#2563eb')

watch(currentTheme, (c) => {
  localStorage.setItem('theme_color', c)
  applyTheme(c)
})

// Apply on load
applyTheme(currentTheme.value)

export function useTheme() {
  function setTheme(hexColor: string) {
    currentTheme.value = hexColor
  }

  return {
    currentTheme,
    presets: THEME_PRESETS,
    setTheme,
  }
}
