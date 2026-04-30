import { computed, unref } from 'vue'
import { useBusinessProfileStore } from '@/stores/businessProfile'

/**
 * وضع «شريك تنفيذ المنصة»: لا سجل عملاء/مركبات مستقل، وفواتير يدوية معطّلة،
 * والتنقل يمرّر من مركز البحث (لوحة / رقم أمر عمل).
 * يُفعّل بـ `effective_feature_matrix.platform_execution_partner === true` أو VITE_PLATFORM_EXECUTION_PARTNER=true
 */
export function usePlatformExecutionPartner() {
  const biz = useBusinessProfileStore()

  const envForced = (): boolean => {
    const v = String(import.meta.env.VITE_PLATFORM_EXECUTION_PARTNER ?? '').trim().toLowerCase()
    return v === 'true' || v === '1' || v === 'on'
  }

  const active = computed(() => {
    if (envForced()) return true
    const ef = unref(biz.effectiveFeatureMatrix) as Record<string, boolean | undefined>
    return unref(biz.loaded) && ef.platform_execution_partner === true
  })

  return { active }
}

/** للاستخدام خارج مكوّنات Vue (مثل حارس الراوتر). */
export function platformExecutionPartnerActiveFromStore(): boolean {
  const v = String(import.meta.env.VITE_PLATFORM_EXECUTION_PARTNER ?? '').trim().toLowerCase()
  if (v === 'true' || v === '1' || v === 'on') return true
  const biz = useBusinessProfileStore()
  const ef = unref(biz.effectiveFeatureMatrix) as Record<string, boolean | undefined>
  return unref(biz.loaded) && ef.platform_execution_partner === true
}
