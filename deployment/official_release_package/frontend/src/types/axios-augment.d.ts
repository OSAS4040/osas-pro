import 'axios'

declare module 'axios' {
  export interface AxiosRequestConfig {
    /**
     * عند true: لا يعرض interceptor رسالة Toast عامة (تُستخدم الشاشة لعرض الخطأ inline أو عبر toast محلي واحد).
     */
    skipGlobalErrorToast?: boolean
  }
}
