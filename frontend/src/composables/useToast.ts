import { ref } from 'vue'

export type ToastType = 'success' | 'error' | 'warning' | 'info'

export interface Toast {
  id: string
  type: ToastType
  title: string
  message?: string
  duration?: number
}

const toasts = ref<Toast[]>([])

function show(type: ToastType, title: string, message?: string, duration = 4000) {
  const id = Math.random().toString(36).slice(2)
  toasts.value.push({ id, type, title, message, duration })
  if (duration > 0) {
    setTimeout(() => dismiss(id), duration)
  }
  return id
}

function dismiss(id: string) {
  toasts.value = toasts.value.filter(t => t.id !== id)
}

export function useToast() {
  return {
    toasts,
    success: (title: string, message?: string) => show('success', title, message),
    error:   (title: string, message?: string) => show('error',   title, message, 6000),
    warning: (title: string, message?: string) => show('warning', title, message),
    info:    (title: string, message?: string) => show('info',    title, message),
    dismiss,
  }
}
