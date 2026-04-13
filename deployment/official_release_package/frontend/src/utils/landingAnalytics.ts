/**
 * تتبّع أحداث صفحة الهبوط بدون جمع بيانات شخصية.
 * - يطلق حدثًا مخصصًا على window لربطه لاحقًا بأي نظام تحليلات.
 * - إن وُجد window.dataLayer (GTM) يُدفَع حدث بسيط.
 */
export type LandingCtaEvent =
  | 'landing_cta_login_header'
  | 'landing_cta_login_hero'
  | 'landing_cta_login_footer'
  | 'landing_cta_login_mobile_bar'
  | 'landing_cta_book_demo_footer'
  | 'landing_cta_book_demo_mobile'
  | 'landing_cta_roi_email'
  | 'landing_nav_roi'
  | 'landing_nav_proof'
  | 'landing_nav_start_steps'
  | 'landing_nav_features'
  | 'landing_nav_smart_compare'
  | 'landing_pain_select'
  | 'landing_feature_showcase_tab'
  | 'landing_nav_header_features'
  | 'landing_nav_header_atlas'
  | 'landing_nav_header_compare'
  | 'landing_nav_header_roi'
  | 'landing_nav_header_pricing'
  | 'landing_nav_header_faq'
  | 'landing_plan_cta'
  | 'landing_pricing_contact_mail'
  | 'landing_nav_hero_explore_open'
  | 'landing_persona_quick_jump'

export function trackLandingCta(event: LandingCtaEvent): void {
  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console -- تتبّع تطويري فقط
    console.debug('[landing]', event)
  }
  try {
    window.dispatchEvent(new CustomEvent('asaspro:landing', { detail: { event } }))
  } catch {
    /* تجاهل */
  }
  const dl = (window as Window & { dataLayer?: unknown[] }).dataLayer
  if (Array.isArray(dl)) {
    dl.push({ event: 'asaspro_landing', landing_event: event })
  }
}
