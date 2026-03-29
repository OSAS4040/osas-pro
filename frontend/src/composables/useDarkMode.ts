import { ref, watch } from 'vue'

const isDark = ref(localStorage.getItem('theme') === 'dark' ||
  (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches))

function applyTheme() {
  document.documentElement.classList.toggle('dark', isDark.value)
}

applyTheme()

watch(isDark, v => {
  localStorage.setItem('theme', v ? 'dark' : 'light')
  applyTheme()
})

export function useDarkMode() {
  return {
    isDark,
    toggle: () => { isDark.value = !isDark.value },
    setDark:  () => { isDark.value = true },
    setLight: () => { isDark.value = false },
  }
}
