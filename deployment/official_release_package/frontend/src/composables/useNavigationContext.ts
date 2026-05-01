import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useBusinessProfileStore } from '@/stores/businessProfile'

/**
 * مصدر واحد لحسم البوابة والقائمة بعد اكتمال الجلسة — بدون افتراضات أثناء التحميل.
 */
export type ResolvedPrincipalPortal = 'staff' | 'platform' | 'fleet' | 'customer' | 'onboarding' | 'unknown'

export function useNavigationContext() {
  const auth = useAuthStore()
  const biz = useBusinessProfileStore()

  const needsTenantFeatureMatrix = computed(() => {
    if (!auth.sessionResolved) return false
    if (!auth.isAuthenticated) return false
    if (!auth.isStaff || auth.isFleet || auth.isCustomer || auth.isPhoneOnboarding) return false
    return typeof auth.user?.company_id === 'number' && auth.user.company_id > 0
  })

  /** جاهز لعرض شريط جانبي المستأجر (قوائم تتطلب معرفة وحدات النشاط) */
  const staffShellReady = computed(() => {
    if (!auth.sessionResolved) return false
    if (!auth.isAuthenticated) return true
    if (!needsTenantFeatureMatrix.value) return true
    return biz.loaded
  })

  const resolvedPortal = computed((): ResolvedPrincipalPortal => {
    if (!auth.sessionResolved || !auth.user) return 'unknown'
    if (auth.isPhoneOnboarding) return 'onboarding'
    if (auth.isFleet) return 'fleet'
    if (auth.isCustomer) return 'customer'
    const g = auth.accountContext?.guard_hint
    if (g === 'platform') return 'platform'
    return 'staff'
  })

  return { needsTenantFeatureMatrix, staffShellReady, resolvedPortal }
}
