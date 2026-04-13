import { reactive } from 'vue'

export type AppConfirmVariant = 'default' | 'danger'

export interface AppConfirmOptions {
  title?: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: AppConfirmVariant
}

/** حالة مشتركة لـ SystemConfirmModal (مركّبة مرة واحدة في AppLayout). */
export const appConfirmDialog = reactive({
  visible: false,
  title: 'تأكيد الإجراء',
  message: '',
  confirmLabel: 'تأكيد',
  cancelLabel: 'إلغاء',
  variant: 'default' as AppConfirmVariant,
})

let pending: ((ok: boolean) => void) | null = null

/**
 * بديل عن window.confirm بنفس سلوك Promise، مع واجهة متناسقة مع هوية النظام (ألوان primary / زر خطر للحذف).
 */
export function appConfirm(options: AppConfirmOptions): Promise<boolean> {
  return new Promise((resolve) => {
    appConfirmDialog.title = options.title ?? 'تأكيد الإجراء'
    appConfirmDialog.message = options.message
    appConfirmDialog.confirmLabel = options.confirmLabel ?? 'تأكيد'
    appConfirmDialog.cancelLabel = options.cancelLabel ?? 'إلغاء'
    appConfirmDialog.variant = options.variant ?? 'default'
    appConfirmDialog.visible = true
    pending = resolve
  })
}

export function completeAppConfirm(ok: boolean): void {
  appConfirmDialog.visible = false
  const fn = pending
  pending = null
  fn?.(ok)
}
