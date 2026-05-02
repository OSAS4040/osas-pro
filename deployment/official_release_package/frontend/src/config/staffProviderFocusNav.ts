import { unref, type MaybeRef } from 'vue'
import { pathToStaffNavKey } from '@/lib/staffNavKey'
import { normalizeBusinessType, type BusinessType } from '@/config/businessFeatureProfileDefaults'

/**
 * مسارات تُخفى في «تركيز مزوّد الخدمة» — واجهة أخف لمراكز الصيانة (بدون عملاء/مركبات/موردين/مطابقة/محاسبة تفصيلية…).
 * بعد الدمج الكامل مع «شريك تنفيذ المنصّة» يُستدعَى `applyWalletTopUpReviewerNavOverride`: يُعاد إظهار «طلبات شحن المحفظة»
 * للمستخدمين الذين يملكون `wallet.top_up_requests.review` أو `wallet.top_up_requests.view` ما لم يُدرِج الخادم المفتاح صراحةً في سياسة الإخفاء.
 * يُفعَّل تركيز المزوّد افتراضياً عند `business_type = service_center` بعد تحميل ملف النشاط، أو قسراً بـ VITE_STAFF_NAV_PROVIDER_FOCUS=true.
 */
const STAFF_PROVIDER_FOCUS_HIDDEN_PATHS: readonly string[] = [
  '/customers',
  '/vehicles',
  '/suppliers',
  '/invoices/create',
  '/wallet/top-up-requests',
  '/financial-reconciliation',
  '/ledger',
  '/chart-of-accounts',
  '/zatca',
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

/** مفتاح القائمة لمسار مراجعة طلبات شحن المحفظة (يجب أن يطابق `StaffNavKey` في الخادم). */
export const WALLET_TOP_UP_REQUESTS_NAV_KEY = pathToStaffNavKey('/wallet/top-up-requests')

/**
 * بعد دمج إخفاء التركيز و«شريك التنفيذ»: يُعيد عنصر قائمة مراجعة طلبات الشحن
 * إذا كان المستخدم يملك صلاحية مراجعة/عرض الطلبات ولم يُخفَ المسار صراحةً من سياسة المستأجر.
 */
export function applyWalletTopUpReviewerNavOverride(
  mergedHiddenNavKeys: string[],
  tenantHiddenStaffNavKeys: string[] | undefined | null,
  hasPermission?: (permission: string) => boolean,
): string[] {
  if (!mergedHiddenNavKeys.includes(WALLET_TOP_UP_REQUESTS_NAV_KEY)) return mergedHiddenNavKeys
  if (tenantHiddenStaffNavKeys?.includes(WALLET_TOP_UP_REQUESTS_NAV_KEY)) return mergedHiddenNavKeys
  if (!hasPermission) return mergedHiddenNavKeys
  const allowed =
    hasPermission('wallet.top_up_requests.review') || hasPermission('wallet.top_up_requests.view')
  if (!allowed) return mergedHiddenNavKeys
  return mergedHiddenNavKeys.filter((k) => k !== WALLET_TOP_UP_REQUESTS_NAV_KEY)
}

/** يدمج مفاتيح الإخفاء من الخادم مع وضع التركيز — بدون تكرار. */
export function mergeStaffHiddenNavKeys(
  userHidden: string[] | undefined | null,
  businessType: MaybeRef<BusinessType | string | undefined>,
  profileLoaded: MaybeRef<boolean>,
  /** مشغّلو المنصّة يحتاجون قائمة المستأجر الكاملة أثناء العمل في واجهة الفريق، دون وضع «تركيز المزوّد». */
  skipProviderFocusMerge = false,
): string[] {
  const base = [...(userHidden ?? [])]
  if (skipProviderFocusMerge || !isStaffProviderFocusNavEnabled(businessType, profileLoaded)) return base
  const focusKeys = staffProviderFocusHiddenNavKeySet()
  for (const k of focusKeys) {
    if (!base.includes(k)) base.push(k)
  }
  return base
}
