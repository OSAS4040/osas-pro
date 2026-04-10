import { computed, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

export interface SetupStatusPayload {
  company_profile_ok: boolean
  branches_count: number
  users_count: number
  policies_count: number
  products_count: number
  has_priced_catalog: boolean
  team_ok: boolean
  permissions_ok: boolean
  branch_ok: boolean
  product_ok: boolean
}

const WELCOME_KEY = 'asaspro_setup_welcome_seen'
const CHECKLIST_HIDDEN_KEY = 'asaspro_setup_checklist_hidden'

function storageKey(prefix: string, companyId: number, userId: number): string {
  return `${prefix}:${companyId}:${userId}`
}

/** يعيد تقييم computed بعد تغيير localStorage (مشترك بين كل المكوّنات) */
const uiPersistTick = ref(0)

const loading = ref(false)
const loadError = ref(false)
const status = ref<SetupStatusPayload | null>(null)
const showWelcomeModal = ref(false)

export function useSetupOnboarding() {
  const auth = useAuthStore()

  const companyId = computed(() => auth.user?.company_id ?? 0)
  const userId = computed(() => auth.user?.id ?? 0)

  function readLs(key: string): string | null {
    try {
      if (companyId.value < 1 || userId.value < 1) return null
      return localStorage.getItem(storageKey(key, companyId.value, userId.value))
    } catch {
      return null
    }
  }

  function writeLs(key: string, value: string): void {
    try {
      if (companyId.value < 1 || userId.value < 1) return
      localStorage.setItem(storageKey(key, companyId.value, userId.value), value)
    } catch {
      /* ignore */
    }
  }

  const welcomeSeen = computed({
    get: () => {
      void uiPersistTick.value
      if (companyId.value < 1 || userId.value < 1) return true
      return readLs(WELCOME_KEY) === '1'
    },
    set: (v: boolean) => {
      writeLs(WELCOME_KEY, v ? '1' : '0')
      uiPersistTick.value++
    },
  })

  const checklistHidden = computed(() => {
    void uiPersistTick.value
    if (companyId.value < 1 || userId.value < 1) return true
    try {
      return readLs(CHECKLIST_HIDDEN_KEY) === '1'
    } catch {
      return true
    }
  })

  function revealChecklistFromSettings(): void {
    writeLs(CHECKLIST_HIDDEN_KEY, '0')
    uiPersistTick.value++
  }

  function markWelcomeSeen(): void {
    welcomeSeen.value = true
    showWelcomeModal.value = false
  }

  function dismissChecklistForLater(): void {
    writeLs(CHECKLIST_HIDDEN_KEY, '1')
    uiPersistTick.value++
  }

  async function fetchStatus(): Promise<void> {
    if (!auth.token || companyId.value < 1) {
      status.value = null
      return
    }
    loading.value = true
    loadError.value = false
    try {
      const { data } = await apiClient.get('/onboarding/setup-status')
      status.value = data?.data ?? null
    } catch {
      loadError.value = true
      status.value = null
    } finally {
      loading.value = false
    }
  }

  const stepsDone = computed(() => {
    const s = status.value
    if (!s) return 0
    let n = 0
    if (s.company_profile_ok) n++
    if (s.branch_ok) n++
    if (s.team_ok) n++
    if (s.permissions_ok) n++
    if (s.product_ok) n++
    if (s.has_priced_catalog) n++
    return n
  })

  const totalSteps = 6
  const progressPercent = computed(() => Math.round((stepsDone.value / totalSteps) * 100))

  const setupComplete = computed(() => stepsDone.value >= totalSteps)

  const shouldShowChecklist = computed(() => {
    if (!auth.isManager) return false
    if (checklistHidden.value) return false
    if (setupComplete.value) return false
    return true
  })

  return {
    loading,
    loadError,
    status,
    fetchStatus,
    welcomeSeen,
    checklistHidden,
    showWelcomeModal,
    markWelcomeSeen,
    dismissChecklistForLater,
    revealChecklistFromSettings,
    stepsDone,
    totalSteps,
    progressPercent,
    setupComplete,
    shouldShowChecklist,
  }
}
