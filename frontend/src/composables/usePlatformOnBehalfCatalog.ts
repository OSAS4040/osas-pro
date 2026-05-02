import { computed, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

/** يطابق مفتاح مركز التنفيذ — يحدد شركة شريك التنفيذ لطلبات الـ API والواجهة */
export const PLATFORM_ON_BEHALF_STORAGE_KEY = 'execution_hub_on_behalf_company_id'

/**
 * سياق «العمل بالنيابة عن مزوّد» لصفحات الكتالوج (خدمات/منتجات) عند مشغّلي المنصّة.
 */
export function usePlatformOnBehalfCatalog() {
  const auth = useAuthStore()
  const onBehalfId = ref(0)
  const partnerCompanies = ref<Array<{ id: number; name: string }>>([])

  function syncFromStorage(): void {
    try {
      const raw = localStorage.getItem(PLATFORM_ON_BEHALF_STORAGE_KEY)
      const n = raw ? parseInt(raw, 10) : 0
      onBehalfId.value = !Number.isNaN(n) && n > 0 ? n : 0
    } catch {
      onBehalfId.value = 0
    }
  }

  const isDelegating = computed(() => Boolean(auth.isPlatform) && onBehalfId.value > 0)

  const selectedProviderName = computed(() => {
    const c = partnerCompanies.value.find((x) => x.id === onBehalfId.value)
    return c?.name ?? null
  })

  async function loadPartnerCompanies(): Promise<void> {
    if (!auth.isPlatform) {
      partnerCompanies.value = []
      return
    }
    try {
      const { data } = await apiClient.get<{
        data?: Array<{ id: number; name: string; platform_execution_partner?: boolean }>
      }>('/platform/companies', { params: { per_page: 100 } })
      const rows = data?.data ?? []
      partnerCompanies.value = rows
        .filter((r) => r.platform_execution_partner === true)
        .map((r) => ({ id: r.id, name: r.name }))
    } catch {
      partnerCompanies.value = []
    }
  }

  return {
    onBehalfId,
    partnerCompanies,
    syncFromStorage,
    loadPartnerCompanies,
    isDelegating,
    selectedProviderName,
  }
}
