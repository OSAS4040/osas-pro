/**
 * تحميل Google Tag Manager عند ضبط VITE_GTM_CONTAINER_ID (مثل GTM-XXXXXXX).
 * يُنشئ dataLayer إن لم يكن موجودًا؛ أحداث صفحة الهبوط تُدفع إليه من landingAnalytics.
 */
export function initOptionalGtm(): void {
  const raw = import.meta.env.VITE_GTM_CONTAINER_ID
  if (typeof raw !== 'string' || !raw.trim()) return
  const containerId = raw.trim()
  const w = window as Window & { dataLayer?: unknown[] }
  w.dataLayer = w.dataLayer ?? []
  w.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' })
  const script = document.createElement('script')
  script.async = true
  script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(containerId)}`
  document.head.appendChild(script)
}
