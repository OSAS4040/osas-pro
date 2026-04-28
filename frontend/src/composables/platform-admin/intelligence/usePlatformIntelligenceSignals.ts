import { ref, computed } from 'vue'
import apiClient from '@/lib/apiClient'
import type { PlatformSignal } from '@/types/platform-admin/platformIntelligenceContracts'
import { usePlatformIntelligencePermissionGates } from '@/composables/platform-admin/usePlatformIntelligencePermissionGates'

export function usePlatformIntelligenceSignals() {
  const { gate } = usePlatformIntelligencePermissionGates()
  const canLoad = computed(() => gate('view_signals'))

  const signals = ref<PlatformSignal[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const severityFilter = ref<string | ''>('')

  const filtered = computed(() => {
    const f = severityFilter.value
    if (!f) return signals.value
    return signals.value.filter((s) => s.severity === f)
  })

  async function fetchSignals(): Promise<void> {
    if (!canLoad.value) {
      signals.value = []
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<{ data: PlatformSignal[] }>('/platform/intelligence/signals')
      signals.value = Array.isArray(data.data) ? data.data : []
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : 'load_failed'
      signals.value = []
    } finally {
      loading.value = false
    }
  }

  return {
    canLoad,
    signals,
    filtered,
    loading,
    error,
    severityFilter,
    fetchSignals,
  }
}
