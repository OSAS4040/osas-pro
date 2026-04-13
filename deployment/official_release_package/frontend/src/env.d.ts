/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

/** من package.json أو VITE_APP_VERSION عند البناء */
declare const __APP_VERSION__: string
/** ISO-8601 — لحظة تقييم vite.config أثناء build */
declare const __APP_BUILD_TIME__: string
declare const __GIT_COMMIT__: string
declare const __GIT_BRANCH__: string
/** local | staging | production — من VITE_DEPLOY_ENV أو الافتراضي حسب mode */
declare const __DEPLOY_ENV__: string

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
  export default component
}

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL: string
  readonly VITE_APP_NAME: string
  /** مفتاح JavaScript لخرائط Google (اختياري — بدونها تظهر تعليمات الإعداد في صفحة الخريطة). */
  readonly VITE_GOOGLE_MAPS_API_KEY?: string
  /** Phase 4 Smart Command Center UI (must match backend intelligence flags). */
  readonly VITE_INTELLIGENCE_COMMAND_CENTER?: string
  /**
   * بيئة العرض الرسمية في الحزمة المبنية: production | staging | local
   * (يُفضّل ضبطها في Docker/CI عند `vite build`).
   */
  readonly VITE_DEPLOY_ENV?: string
  /** أصل الموقع العلني (HTTPS) لـ canonical و Open Graph على /landing */
  readonly VITE_PUBLIC_SITE_URL?: string
  /** معرّف حاوية Google Tag Manager — مثل GTM-XXXXXXX */
  readonly VITE_GTM_CONTAINER_ID?: string
  /**
   * بوابات اختيارية مفعّلة في البناء: fleet, customer, admin (مفصولة بفواصل).
   * غير مضبوط أو فارغ = الكل مفعّل (آمن للنشر الحالي).
   */
  readonly VITE_ENABLED_PORTALS?: string
  /**
   * عند `true`: تعرض صفحة /platform/login نص بيانات تجريبية (للتجارب الداخلية فقط — تجنّب الإنتاج العلني).
   */
  readonly VITE_SHOW_PLATFORM_LOGIN_HINT?: string
  /** عند `true`: تعرض /login صندوق بيانات تجريبية (داخلية فقط). */
  readonly VITE_SHOW_LOGIN_DEMO_HINT?: string
  /** عند `false`: يُعطّل مسار الأرشفة الإلكترونية (`/electronic-archive` و `/workshop/hr-archive`). */
  readonly VITE_ELECTRONIC_ARCHIVE?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

/** يُملأ بعد تحميل سكربت Google Maps */
interface Window {
  google?: any
}
