/** نسبة رسوم المنصة على اشتراك/أداء المزود (غير شامل ضريبة القيمة المضافة). */
export const PLATFORM_FEE_RATE = 0.2

export interface MonthlyPriceBreakdown {
  supplierMonthly: number
  platformFee: number
  totalMonthly: number
  isFree: boolean
}

export function monthlyPricingWithPlatform(supplierMonthlyRaw: number | string | null | undefined): MonthlyPriceBreakdown {
  const supplierMonthly = Math.max(0, Math.round(Number(supplierMonthlyRaw) * 100) / 100)
  if (!supplierMonthly) {
    return { supplierMonthly: 0, platformFee: 0, totalMonthly: 0, isFree: true }
  }
  const platformFee = Math.round(supplierMonthly * PLATFORM_FEE_RATE * 100) / 100
  const totalMonthly = Math.round((supplierMonthly + platformFee) * 100) / 100
  return { supplierMonthly, platformFee, totalMonthly, isFree: false }
}

export function formatSar(n: number): string {
  return n.toLocaleString('ar-SA', { maximumFractionDigits: 2, minimumFractionDigits: n % 1 ? 2 : 0 }) + ' ر.س'
}
