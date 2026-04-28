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

const ALL_ON: SubscriptionFeatures = {
  pos: true, invoices: true, work_orders: true,
  fleet: true, reports: true, api_access: true, zatca: true,
}

/** يطابق PlanSeeder — عند غياب ميزات من الـ API نُبقي كل الميزات مفعّلة افتراضيًا */
const FEATURE_FALLBACK: Record<string, SubscriptionFeatures> = {
  trial: { ...ALL_ON },
  basic: { ...ALL_ON },
  professional: { ...ALL_ON },
  enterprise: { ...ALL_ON },
}

/** يُعرض في الشريط العلوي فورًا من `/auth/me` قبل اكتمال طلب `/subscription` */
const PLAN_LABEL_AR: Record<string, string> = {
  trial: 'تجريبي',
  basic: 'أساسي',
  professional: 'احترافي',
  enterprise: 'مؤسسات',
}

export const useSubscriptionStore = defineStore('subscription', () => {
  const planSlug  = ref<string>('trial')
  const planName  = ref<string>('تجريبي')
  const features  = ref<SubscriptionFeatures>({ ...ALL_ON })
  const limits    = ref<SubscriptionLimits>({ max_branches: 1, max_users: 3, max_products: 100 })
  const loaded    = ref(false)

  function hasFeature(key: string): boolean {
    return features.value[key] === true
  }

  function parseFeatures(raw: any): SubscriptionFeatures {
    const base: SubscriptionFeatures = { ...ALL_ON }
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

  /** يملأ الشارة من `user.subscription` القادم مع تسجيل الدخول أو fetchMe */
  function hydrateFromAuthUser(user: {
    subscription?: {
      plan: string | null
      max_branches?: number | null
      max_users?: number | null
    } | null
  } | null): void {
    const s = user?.subscription
    const slug = s?.plan
    if (!slug) return
    planSlug.value = slug
    planName.value = PLAN_LABEL_AR[slug] ?? slug
    limits.value = {
      max_branches: s.max_branches ?? limits.value.max_branches,
      max_users:    s.max_users    ?? limits.value.max_users,
      max_products: limits.value.max_products,
    }
  }

  async function loadSubscription(force = false): Promise<void> {
    if (loaded.value && !force) return
    try {
      const { data } = await apiClient.get('/subscription')
      const payload = data.data ?? data
      const plan    = payload.plan ?? null
      const sub     = payload.subscription ?? payload

      const slug = String(plan?.slug ?? sub?.plan ?? 'trial')
      planSlug.value = slug
      planName.value = plan?.name_ar ?? PLAN_LABEL_AR[slug] ?? slug

      const raw = plan?.features ?? sub?.features
      let merged = parseFeatures(raw)
      const fb = FEATURE_FALLBACK[slug]
      if (fb) {
        merged = { ...fb, ...merged }
      }
      const anyOn = Object.values(merged).some((v) => v === true)
      if (!anyOn && fb) {
        merged = { ...fb }
      }
      features.value = merged

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
    features.value = { ...ALL_ON }
    limits.value = { max_branches: 1, max_users: 3, max_products: 100 }
    loaded.value = false
  }

  return { planSlug, planName, features, limits, loaded, hasFeature, loadSubscription, hydrateFromAuthUser, reset }
})
