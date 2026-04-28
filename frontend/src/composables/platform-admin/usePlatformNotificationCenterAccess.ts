import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

/** وصول مركز التنبيهات (API + واجهة الجرس): قراءة الإشعارات أو إدارة اشتراكات المنصة */
export function usePlatformNotificationCenterAccess() {
  const auth = useAuthStore()
  const canAccess = computed(
    () =>
      auth.hasPermission('platform.notifications.read') || auth.hasPermission('platform.subscription.manage'),
  )
  return { canAccess }
}
