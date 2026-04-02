import axios, { type AxiosInstance, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { v4 as uuidv4 } from 'uuid'
import { localizeApiErrorPayload, localizeBackendMessage, uiByLang } from '@/utils/runtimeLocale'
import { friendlyFieldLabel } from '@/utils/friendlyFieldLabel'

/** Prefer relative `/api/v1` behind nginx+Vite proxy; set VITE_API_BASE_URL only when API is on another host. */
const apiBase =
  typeof import.meta.env.VITE_API_BASE_URL === 'string' &&
  import.meta.env.VITE_API_BASE_URL.trim() !== ''
    ? import.meta.env.VITE_API_BASE_URL.trim()
    : '/api/v1'

function attachApiInterceptors(instance: AxiosInstance): void {
  instance.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    config.headers = config.headers ?? {}
    config.headers.Accept = 'application/json'
    if (config.data instanceof FormData) {
      delete config.headers['Content-Type']
    } else if (
      config.method &&
      ['post', 'put', 'patch'].includes(config.method.toLowerCase()) &&
      !(config.data instanceof FormData)
    ) {
      const ct = config.headers['Content-Type'] as string | undefined
      if (!ct || ct === 'application/json') {
        config.headers['Content-Type'] = 'application/json'
      }
    }

    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }

    const clientRequestId = uuidv4()
    config.headers['X-Client-Request-Id'] = clientRequestId
    config.headers['X-Request-Id'] = clientRequestId

    return config
  })

  instance.interceptors.response.use(
    (response: AxiosResponse) => response,
    (error) => {
      if (error?.response?.data) {
        error.response.data = localizeApiErrorPayload(error.response.data)
      }

      if (error.response?.status === 401) {
        const path = String(error.config?.url ?? '')
        if (path.includes('auth/login')) {
          return Promise.reject(error)
        }
        localStorage.removeItem('auth_token')
        window.location.href = '/login'
        return Promise.reject(error)
      }

      if (error.response?.status === 422) {
        const d = error.response.data
        const errors = d?.errors
        const firstField = errors ? String(Object.keys(errors)[0] ?? '') : ''
        const fieldLabel = friendlyFieldLabel(firstField)
        const firstMsgRaw = errors
          ? (Object.values(errors).flat()[0] as string)
          : (d?.message ?? 'بيانات غير صحيحة')
        const firstMsg = localizeBackendMessage(firstMsgRaw)
        const traceId = String(d?.trace_id ?? '').trim()
        const details = [
          fieldLabel ? uiByLang(`الحقل: ${fieldLabel}`, `Field: ${fieldLabel}`) : '',
          traceId ? uiByLang(`رمز التتبع: ${traceId}`, `Trace ID: ${traceId}`) : '',
        ]
          .filter(Boolean)
          .join(' — ')
        import('@/composables/useToast').then(({ useToast }) => {
          useToast().warning(uiByLang('تحقق من البيانات', 'Check your input'), details ? `${firstMsg}\n${details}` : firstMsg)
        })
      } else if (error.response?.status === 402) {
        const url402 = String(error.config?.url ?? '')
        if (!url402.includes('auth/login')) {
          import('@/composables/useToast').then(({ useToast }) => {
            useToast().warning(
              uiByLang('الاشتراك', 'Subscription'),
              localizeBackendMessage(error.response?.data?.message) || uiByLang('لا يمكن المتابعة — اشتراك غير نشط أو موقوف.', 'Cannot continue: subscription is inactive or suspended.')
            )
          })
        }
      } else if (error.response?.status === 423) {
        import('@/composables/useToast').then(({ useToast }) => {
          useToast().warning(
            uiByLang('وضع القراءة فقط', 'Read-only mode'),
            localizeBackendMessage(error.response?.data?.message) || uiByLang('فترة السماح: لا يُسمح بتعديل البيانات حتى تجديد الاشتراك.', 'Grace period: editing is blocked until renewal.')
          )
        })
      } else if (error.response?.status >= 500) {
        import('@/composables/useToast').then(({ useToast }) => {
          useToast().error(
            uiByLang('خطأ في الخادم', 'Server error'),
            localizeBackendMessage(error.response?.data?.message) || uiByLang('حدث خطأ غير متوقع', 'Unexpected server error')
          )
        })
      } else if (!error.response) {
        import('@/composables/useToast').then(({ useToast }) => {
          useToast().error(uiByLang('تعذّر الاتصال', 'Connection error'), uiByLang('تحقق من الشبكة أو أعد المحاولة', 'Check your network and try again'))
        })
      }

      return Promise.reject(error)
    }
  )
}

const apiClient: AxiosInstance = axios.create({
  baseURL: apiBase,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
})

attachApiInterceptors(apiClient)

let browserAxiosSynced = false

/** Same headers/interceptors as apiClient for raw `axios.get('/api/v1/...')` call sites. */
export function syncBrowserAxiosWithApiClient(): void {
  if (browserAxiosSynced) {
    return
  }
  browserAxiosSynced = true
  axios.defaults.headers.common.Accept = 'application/json'
  axios.defaults.headers.common['Content-Type'] = 'application/json'
  attachApiInterceptors(axios)
}

export function withIdempotency(config: InternalAxiosRequestConfig = {} as InternalAxiosRequestConfig): InternalAxiosRequestConfig {
  config.headers = config.headers ?? {}
  config.headers['Idempotency-Key'] = uuidv4()
  return config
}

export default apiClient
