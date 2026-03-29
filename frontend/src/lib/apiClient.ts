import axios, { type AxiosInstance, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { v4 as uuidv4 } from 'uuid'

/** Prefer relative `/api/v1` behind nginx+Vite proxy; set VITE_API_BASE_URL only when API is on another host. */
const apiBase =
  typeof import.meta.env.VITE_API_BASE_URL === 'string' &&
  import.meta.env.VITE_API_BASE_URL.trim() !== ''
    ? import.meta.env.VITE_API_BASE_URL.trim()
    : '/api/v1'

const apiClient: AxiosInstance = axios.create({
  baseURL: apiBase,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 30000,
})

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  const clientRequestId = uuidv4()
  config.headers['X-Client-Request-Id'] = clientRequestId
  config.headers['X-Request-Id'] = clientRequestId

  return config
})

apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
      return Promise.reject(error)
    }

    if (error.response?.status === 422) {
      const d = error.response.data
      const errors = d?.errors
      const firstMsg = errors
        ? (Object.values(errors).flat()[0] as string)
        : (d?.message ?? 'بيانات غير صحيحة')
      import('@/composables/useToast').then(({ useToast }) => {
        useToast().warning('تحقق من البيانات', firstMsg)
      })
    } else if (error.response?.status === 402) {
      import('@/composables/useToast').then(({ useToast }) => {
        useToast().warning(
          'الاشتراك',
          error.response?.data?.message ?? 'لا يمكن المتابعة — اشتراك غير نشط أو موقوف.'
        )
      })
    } else if (error.response?.status === 423) {
      import('@/composables/useToast').then(({ useToast }) => {
        useToast().warning(
          'وضع القراءة فقط',
          error.response?.data?.message ?? 'فترة السماح: لا يُسمح بتعديل البيانات حتى تجديد الاشتراك.'
        )
      })
    } else if (error.response?.status >= 500) {
      import('@/composables/useToast').then(({ useToast }) => {
        useToast().error('خطأ في الخادم', error.response?.data?.message ?? 'حدث خطأ غير متوقع')
      })
    } else if (!error.response) {
      import('@/composables/useToast').then(({ useToast }) => {
        useToast().error('تعذّر الاتصال', 'تحقق من الشبكة أو أعد المحاولة')
      })
    }

    return Promise.reject(error)
  }
)

export function withIdempotency(config: InternalAxiosRequestConfig = {} as InternalAxiosRequestConfig): InternalAxiosRequestConfig {
  config.headers = config.headers ?? {}
  config.headers['Idempotency-Key'] = uuidv4()
  return config
}

export default apiClient
