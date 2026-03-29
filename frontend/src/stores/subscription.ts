import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/lib/apiClient'

export interface SubscriptionFeatures {
  pos: boolean
  invoices: boolean
  work_orders: boolean
  fleet: boolean
  reports: boolean
  api_access: boolean
  zatca: boolean
  [key: string]: boolean
}

export interface SubscriptionLimits {
  max_branches: number
  max_users: number
  max_products: number
}

export const useSubscriptionStore = defineStore('subscription', () => {
  const planSlug  = ref<string>('trial')
  const planName  = ref<string>('تجريبي')
  const features  = ref<SubscriptionFeatures>({
    pos: true, invoices: true, work_orders: false,
    fleet: false, reports: false, api_access: false, zatca: false,
  })
  const limits    = ref<SubscriptionLimits>({ max_branches: 1, max_users: 3, max_products: 100 })
  const loaded    = ref(false)

  function hasFeature(key: string): boolean {
    return features.value[key] === true
  }

  function parseFeatures(raw: any): SubscriptionFeatures {
    const base: SubscriptionFeatures = {
      pos: false, invoices: false, work_orders: false,
      fleet: false, reports: false, api_access: false, zatca: false,
    }
    if (!raw) return base
    if (Array.isArray(raw)) {
      for (const key of raw) {
        const k = key === 'api' ? 'api_access' : key
        base[k] = true
      }
      return base
    }
    return { ...base, ...raw }
  }

  async function loadSubscription(): Promise<void> {
    if (loaded.value) return
    try {
      const { data } = await apiClient.get('/subscription')
      const payload = data.data ?? data
      const plan    = payload.plan ?? null
      const sub     = payload.subscription ?? payload

      planSlug.value = plan?.slug ?? sub?.plan ?? 'trial'
      planName.value = plan?.name_ar ?? 'احترافي'
      features.value = parseFeatures(plan?.features ?? sub?.features)
      limits.value   = {
        max_branches: plan?.max_branches ?? sub?.max_branches ?? 1,
        max_users:    plan?.max_users    ?? sub?.max_users    ?? 3,
        max_products: plan?.max_products ?? sub?.max_products ?? 100,
      }
      loaded.value = true
    } catch {
      // keep defaults
    }
  }

  function reset(): void {
    planSlug.value = 'trial'
    planName.value = 'تجريبي'
    features.value = {
      pos: true, invoices: true, work_orders: false,
      fleet: false, reports: false, api_access: false, zatca: false,
    }
    limits.value = { max_branches: 1, max_users: 3, max_products: 100 }
    loaded.value = false
  }

  return { planSlug, planName, features, limits, loaded, hasFeature, loadSubscription, reset }
})
