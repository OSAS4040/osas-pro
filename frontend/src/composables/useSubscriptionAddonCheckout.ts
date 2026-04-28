/**
 * ربط اختياري ببوابة دفع خارجية قبل/بعد تفعيل الإضافة على الاشتراك.
 * ضع في البيئة: VITE_SUBSCRIPTION_ADDON_CHECKOUT_URL مع placeholder {slug} إن لزم
 * مثال: https://billing.example.com/addons?addon={slug}
 */
export function getAddonExternalCheckoutUrl(addonSlug: string): string | null {
  const raw = import.meta.env.VITE_SUBSCRIPTION_ADDON_CHECKOUT_URL
  if (typeof raw !== 'string' || !raw.trim()) {
    return null
  }
  const base = raw.trim()
  if (base.includes('{slug}')) {
    return base.split('{slug}').join(encodeURIComponent(addonSlug))
  }
  const sep = base.includes('?') ? '&' : '?'
  return `${base}${sep}addon=${encodeURIComponent(addonSlug)}`
}

export function isAddonCheckoutGatewayConfigured(): boolean {
  return getAddonExternalCheckoutUrl('test') !== null
}
