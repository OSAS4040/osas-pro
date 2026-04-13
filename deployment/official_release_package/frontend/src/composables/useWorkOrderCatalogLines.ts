import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'

export interface CatalogLineItem {
  item_type: string
  service_id: number | null
  name: string
  quantity: number
  unit_price: number
  tax_rate: number
  product_id: number | null
  pricing_ok: boolean
  pricing_loading: boolean
  pricing_error: string
  pricing_source_label_ar: string
}

const PRICING_SOURCE_AR: Record<string, string> = {
  customer_specific: 'سعر خاص للعميل',
  customer_group: 'سعر مجموعة العملاء',
  contract: 'سعر عقد / اتفاقية',
  general_policy: 'سعر عام (سياسة)',
  general_service_base: 'السعر العام للخدمة',
}

export function emptyCatalogLine(): CatalogLineItem {
  return {
    item_type: 'service',
    service_id: null,
    name: '',
    quantity: 1,
    unit_price: 0,
    tax_rate: 15,
    product_id: null,
    pricing_ok: false,
    pricing_loading: false,
    pricing_error: '',
    pricing_source_label_ar: '',
  }
}

export function pricingSourceLabelAr(source: string | null | undefined): string {
  if (!source) return ''
  return PRICING_SOURCE_AR[source] ?? source
}

/** تهيئة سطر تعديل من بند محفوظ في الخادم */
export function catalogLineFromApiItem(apiItem: Record<string, unknown>): CatalogLineItem {
  const sid = apiItem.service_id != null ? Number(apiItem.service_id) : null
  const hasService = sid != null && !Number.isNaN(sid)
  const src = typeof apiItem.pricing_source === 'string' ? apiItem.pricing_source : ''
  return {
    item_type: String(apiItem.item_type ?? 'service'),
    service_id: hasService ? sid : null,
    name: String(apiItem.name ?? ''),
    quantity: Number(apiItem.quantity) || 1,
    unit_price: Number(apiItem.unit_price) || 0,
    tax_rate: (() => {
      const tr = Number(apiItem.tax_rate)
      return Number.isFinite(tr) ? tr : 15
    })(),
    product_id: apiItem.product_id != null ? Number(apiItem.product_id) : null,
    /** بنود محفوظة أو يدوية — التحقق من السعر المعتمد يطبق فقط عند service_id */
    pricing_ok: true,
    pricing_loading: false,
    pricing_error: '',
    pricing_source_label_ar: src ? pricingSourceLabelAr(src) : '',
  }
}

export function lineTotalDisplay(item: CatalogLineItem): string {
  const sub = item.quantity * item.unit_price
  const tax = sub * (item.tax_rate / 100)
  return (sub + tax).toFixed(2)
}

export function totalAmountDisplay(items: CatalogLineItem[]): string {
  return items
    .reduce((acc, item) => {
      const sub = item.quantity * item.unit_price
      return acc + sub + sub * (item.tax_rate / 100)
    }, 0)
    .toFixed(2)
}

export function canSubmitCatalogLines(
  items: CatalogLineItem[],
  customerId: string,
  vehicleId: string,
): boolean {
  if (!items.length) return false
  const hasParty = Boolean(String(customerId).trim() && String(vehicleId).trim())
  return items.every((item) => {
    const q = Number(item.quantity)
    if (!Number.isFinite(q) || q <= 0) return false
    if (item.service_id != null) {
      return hasParty && item.pricing_ok && !item.pricing_loading
    }
    const nameOk = typeof item.name === 'string' && item.name.trim().length > 0
    const p = Number(item.unit_price)
    const tax = Number(item.tax_rate)
    return (
      nameOk &&
      Number.isFinite(p) &&
      p >= 0 &&
      Number.isFinite(tax) &&
      tax >= 0 &&
      tax <= 100
    )
  })
}

export async function loadCatalogLinePreview(
  item: CatalogLineItem,
  customerId: string,
  vehicleId: string,
  services: Array<{ id: number; name?: string; name_ar?: string }>,
): Promise<void> {
  if (item.service_id == null) return
  if (!String(customerId).trim() || !String(vehicleId).trim()) {
    item.pricing_ok = false
    item.pricing_error = 'اختر العميل والمركبة أولاً لعرض السعر المعتمد من العقد أو سياسة التسعير.'
    return
  }
  item.pricing_loading = true
  item.pricing_error = ''
  try {
    const { data } = await apiClient.post(
      '/work-orders/line-pricing-preview',
      {
        customer_id: Number(customerId),
        vehicle_id: Number(vehicleId),
        service_id: Number(item.service_id),
        quantity: Number(item.quantity) || 1,
      },
      { skipGlobalErrorToast: true },
    )
    const row = data.data
    item.unit_price = Number(row.unit_price)
    item.tax_rate = Number(row.tax_rate)
    item.pricing_source_label_ar = String(row.pricing_source_label_ar ?? '')
    item.pricing_ok = true
    const svc = services.find((s) => s.id === item.service_id)
    item.name = String(svc?.name_ar || svc?.name || '').trim()
  } catch (e: unknown) {
    item.pricing_ok = false
    item.pricing_error = summarizeAxiosError(e)
  } finally {
    item.pricing_loading = false
  }
}

export function buildItemsApiPayload(items: CatalogLineItem[]): Array<Record<string, unknown>> {
  return items.map((i) => {
    const base: Record<string, unknown> = {
      item_type: i.item_type,
      quantity: Number(i.quantity),
      product_id: i.product_id,
    }
    if (i.service_id != null) {
      base.service_id = Number(i.service_id)
      return base
    }
    return {
      ...base,
      name: String(i.name || '').trim(),
      unit_price: Number(i.unit_price),
      tax_rate: Number(i.tax_rate),
    }
  })
}
