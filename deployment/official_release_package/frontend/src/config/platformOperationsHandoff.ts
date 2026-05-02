import type { RouteLocationRaw } from 'vue-router'

/** نص ظاهر إلزامي على أي عنصر يخرج المستخدم من مسار إدارة المنصة */
export const PLATFORM_OPERATIONS_EXIT_VISIBLE = 'الانتقال إلى بوابة التشغيل (خارج سياق المنصة)'

/** تلميح أداة ووصف للقارئ الشاشي */
export const PLATFORM_OPERATIONS_EXIT_TOOLTIP = 'ستغادر لوحة إدارة المنصة وتنتقل إلى واجهة فريق العمل'

export function platformOperationsExitAriaLabel(shortActionLabel: string): string {
  return `${shortActionLabel}. ${PLATFORM_OPERATIONS_EXIT_VISIBLE} ${PLATFORM_OPERATIONS_EXIT_TOOLTIP}`
}

/**
 * وسيط URL يفرّق «الدخول الفعلي إلى واجهة المستأجر» عن زيارة `/` الخاطئة لمشغّل المنصة
 * (يُطابقه الحارس مع {@see isTenantShellQuery}).
 */
export const PLATFORM_TENANT_SHELL_QUERY_KEY = 'shell'
export const PLATFORM_TENANT_SHELL_QUERY_VALUE = 'tenant'

export function tenantStaffHomeRoute(): RouteLocationRaw {
  return {
    path: '/',
    query: { [PLATFORM_TENANT_SHELL_QUERY_KEY]: PLATFORM_TENANT_SHELL_QUERY_VALUE },
  }
}

export function isTenantShellQuery(query: Record<string, unknown | undefined> | undefined): boolean {
  return String(query?.[PLATFORM_TENANT_SHELL_QUERY_KEY] ?? '') === PLATFORM_TENANT_SHELL_QUERY_VALUE
}
