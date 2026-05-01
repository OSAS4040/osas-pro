import { ref, computed } from 'vue'
import { isAxiosError } from 'axios'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'
import { PLATFORM_INTELLIGENCE_PERMISSIONS } from '@/types/platform-admin/platformIntelligencePermissionMatrix'

export function usePlatformCommandSurface() {
  const auth = useAuthStore()
  const canView = computed(() => auth.hasPermission(PLATFORM_INTELLIGENCE_PERMISSIONS.incidentsRead))

  const payload = ref<Record<string, unknown> | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchSurface(): Promise<void> {
    if (!canView.value) {
      payload.value = null
      return
    }
    loading.value = true
    error.value = null
    try {
      const { data } = await apiClient.get<Record<string, unknown>>('/platform/intelligence/command-surface')
      payload.value = data
    } catch (e: unknown) {
      if (isAxiosError(e) && e.response?.data && typeof e.response.data === 'object') {
        payload.value = e.response.data as Record<string, unknown>
        error.value = String((e.response.data as Record<string, unknown>).message ?? e.message ?? 'command_surface_failed')
      } else {
        error.value = e instanceof Error ? e.message : 'command_surface_failed'
        payload.value = null
      }
    } finally {
      loading.value = false
    }
  }

  return { canView, payload, loading, error, fetchSurface }
}
