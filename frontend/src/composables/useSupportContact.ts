/**
 * بيانات التواصل مع الدعم / مدير النظام — تُضبط من متغيرات البيئة عند النشر.
 */
export function useSupportContact() {
  const email = String(import.meta.env.VITE_SUPPORT_EMAIL ?? '').trim() || 'sales@asaspro.sa'
  const phone = String(import.meta.env.VITE_SUPPORT_PHONE ?? '').trim()
  const whatsapp = String(import.meta.env.VITE_SUPPORT_WHATSAPP ?? '').trim()

  const waHref = whatsapp
    ? `https://wa.me/${whatsapp.replace(/\D/g, '')}`
    : ''

  return { email, phone, whatsapp, waHref }
}
