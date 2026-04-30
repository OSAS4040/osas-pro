import { unref, type MaybeRef } from 'vue'
import { pathToStaffNavKey } from '@/lib/staffNavKey'
import { normalizeBusinessType, type BusinessType } from '@/config/businessFeatureProfileDefaults'

/**
 * مسارات تُخفى في «تركيز مزوّد الخدمة» — واجهة أخف لمراكز الصيانة (بدون سجل عملاء/مركبات مستقل لدى المزود، وبدون POS/CRM/HR/ذكاء تشغيلي إضافي…).
 * يُفعَّل افتراضياً عند `business_type = service_center` بعد تحميل ملف النشاط، أو قسراً بـ VITE_STAFF_NAV_PROVIDER_FOCUS=true.
 */
const STAFF_PROVIDER_FOCUS_HIDDEN_PATHS: readonly string[] = [
  '/customers',
  '/vehicles',
  '/pos',
  '/bays',
  '/bookings',
  '/meetings',
  '/bays/heatmap',
  '/crm/quotes',
  '/crm/relations',
  '/fleet/wallet',
  '/business-intelligence',
  '/governance',
  '/internal/intelligence',
  '/referrals',
  '/plugins',
  '/documents/company',
  '/activity',
  '/settings/integrations',
  '/settings/api-keys',
  '/workshop/employees',
  '/workshop/tasks',
  '/workshop/attendance',
  '/workshop/leaves',
  '/workshop/salaries',
  '/workshop/commissions',
  '/workshop/commission-policies',
  '/workshop/hr-comms',
  '/workshop/hr-archive',
  '/workshop/hr-signatures',
  '/workshop/wage-protection',
  '/compliance/labor-law',
  '/electronic-archive',
  '/fixed-assets',
]

let _focusKeySet: Set<string> | null = null

function staffProviderFocusHiddenNavKeySet(): Set<string> {
  if (_focusKeySet) return _focusKeySet
  const keys = new Set<string>()
  for (const p of STAFF_PROVIDER_FOCUS_HIDDEN_PATHS) {
    keys.add(pathToStaffNavKey(p))
  }
  _focusKeySet = keys
  return keys
}

export function isStaffProviderFocusNavEnabled(
  businessType: MaybeRef<BusinessType | string | undefined>,
  profileLoaded: MaybeRef<boolean>,
): boolean {
  const raw = String(import.meta.env.VITE_STAFF_NAV_PROVIDER_FOCUS ?? '').trim().toLowerCase()
  if (raw === 'false' || raw === '0' || raw === 'off') return false
  if (raw === 'true' || raw === '1' || raw === 'on') return true
  if (!unref(profileLoaded)) return false
  return normalizeBusinessType(unref(businessType)) === 'service_center'
}

/** يدمج مفاتيح الإخفاء من الخادم مع وضع التركيز — بدون تكرار. */
export function mergeStaffHiddenNavKeys(
  userHidden: string[] | undefined | null,
  businessType: MaybeRef<BusinessType | string | undefined>,
  profileLoaded: MaybeRef<boolean>,
): string[] {
  const base = [...(userHidden ?? [])]
  if (!isStaffProviderFocusNavEnabled(businessType, profileLoaded)) return base
  const focusKeys = staffProviderFocusHiddenNavKeySet()
  for (const k of focusKeys) {
    if (!base.includes(k)) base.push(k)
  }
  return base
}
