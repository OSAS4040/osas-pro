import { ref, watch } from 'vue'

type ThemeMode = 'auto' | 'manual'

const themeMode = ref<ThemeMode>((localStorage.getItem('theme_mode') as ThemeMode) || 'manual')
const isDark = ref(false)

function resolveAutoIsDark(): boolean {
  const hour = new Date().getHours()
  return hour >= 18 || hour < 7
}

function getInitialTheme(): boolean {
  const stored = localStorage.getItem('theme')
  if (themeMode.value === 'auto') return resolveAutoIsDark()
  if (stored === 'dark') return true
  if (stored === 'light') return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function applyTheme() {
  document.documentElement.classList.toggle('dark', isDark.value)
  document.documentElement.style.colorScheme = isDark.value ? 'dark' : 'light'
}

function syncAutoTheme() {
  if (themeMode.value !== 'auto') return
  isDark.value = resolveAutoIsDark()
}

isDark.value = getInitialTheme()
applyTheme()

watch(isDark, v => {
  if (themeMode.value === 'manual') {
    localStorage.setItem('theme', v ? 'dark' : 'light')
  }
  applyTheme()
})

watch(themeMode, (mode) => {
  localStorage.setItem('theme_mode', mode)
  if (mode === 'auto') syncAutoTheme()
})

setInterval(syncAutoTheme, 60_000)

export function useDarkMode() {
  return {
    isDark,
    themeMode,
    toggle: () => {
      themeMode.value = 'manual'
      isDark.value = !isDark.value
    },
    setDark:  () => {
      themeMode.value = 'manual'
      isDark.value = true
    },
    setLight: () => {
      themeMode.value = 'manual'
      isDark.value = false
    },
    setAuto: () => {
      themeMode.value = 'auto'
      syncAutoTheme()
    },
  }
}
