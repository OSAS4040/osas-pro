import { onMounted, onUnmounted, ref } from 'vue'

/** حدث غير موحّد في كل المتصفحات — يكفي لـ Chrome/Edge على سطح المكتب وأندرويد */
interface BeforeInstallPromptEventLike extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: string }>
}

function isBip(e: Event): e is BeforeInstallPromptEventLike {
  return typeof (e as BeforeInstallPromptEventLike).prompt === 'function'
}

export function usePwaInstall() {
  const canPromptInstall = ref(false)
  const deferred = ref<BeforeInstallPromptEventLike | null>(null)
  const originUrl = ref('')

  function onBeforeInstallPrompt(e: Event): void {
    if (!isBip(e)) return
    e.preventDefault()
    deferred.value = e
    canPromptInstall.value = true
  }

  onMounted(() => {
    originUrl.value = typeof window !== 'undefined' ? window.location.origin : ''
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt)
  })

  onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt)
  })

  async function promptInstall(): Promise<void> {
    const d = deferred.value
    if (!d) return
    await d.prompt()
    await d.userChoice.catch(() => {})
    deferred.value = null
    canPromptInstall.value = false
  }

  async function copyOriginUrl(): Promise<boolean> {
    const u = originUrl.value || (typeof window !== 'undefined' ? window.location.origin : '')
    if (!u || !navigator.clipboard?.writeText) return false
    try {
      await navigator.clipboard.writeText(u)
      return true
    } catch {
      return false
    }
  }

  return {
    originUrl,
    canPromptInstall,
    promptInstall,
    copyOriginUrl,
  }
}
