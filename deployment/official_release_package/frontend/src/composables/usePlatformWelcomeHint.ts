/**
 * حالة إظهار شريط الترحيب في إدارة المنصة.
 * التخزين محلي للمتصفح فقط — لا يُرسل إلى الخادم.
 */
import { computed, readonly, ref } from 'vue'

const STORAGE_KEY = 'verdent:platformWelcomeHintDismissed'
const LEGACY_SESSION_KEY = 'verdent:platformWelcomeHintDismissedSession'

const dismissed = ref(false)

let didInit = false

function init(): void {
  if (didInit) return
  didInit = true
  if (typeof window === 'undefined') return
  try {
    if (localStorage.getItem(STORAGE_KEY) === '1') {
      dismissed.value = true
      return
    }
    if (sessionStorage.getItem(LEGACY_SESSION_KEY) === '1') {
      dismissed.value = true
      localStorage.setItem(STORAGE_KEY, '1')
      sessionStorage.removeItem(LEGACY_SESSION_KEY)
    }
  } catch {
    /* وضع خاص أو حظر التخزين */
  }
}

export function usePlatformWelcomeHint() {
  init()

  const showWelcomeStrip = computed(() => !dismissed.value)

  function dismiss(): void {
    dismissed.value = true
    try {
      localStorage.setItem(STORAGE_KEY, '1')
      if (sessionStorage.getItem(LEGACY_SESSION_KEY)) {
        sessionStorage.removeItem(LEGACY_SESSION_KEY)
      }
    } catch {
      /* ignore */
    }
  }

  function resetHint(): void {
    dismissed.value = false
    try {
      localStorage.removeItem(STORAGE_KEY)
    } catch {
      /* ignore */
    }
  }

  return {
    showWelcomeStrip,
    isDismissed: readonly(dismissed),
    dismiss,
    resetHint,
  }
}
