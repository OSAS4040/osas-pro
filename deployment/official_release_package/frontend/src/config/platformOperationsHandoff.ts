/** نص ظاهر إلزامي على أي عنصر يخرج المستخدم من مسار إدارة المنصة */
export const PLATFORM_OPERATIONS_EXIT_VISIBLE = 'الانتقال إلى بوابة التشغيل (خارج سياق المنصة)'

/** تلميح أداة ووصف للقارئ الشاشي */
export const PLATFORM_OPERATIONS_EXIT_TOOLTIP = 'ستغادر لوحة إدارة المنصة وتنتقل إلى واجهة فريق العمل'

export function platformOperationsExitAriaLabel(shortActionLabel: string): string {
  return `${shortActionLabel}. ${PLATFORM_OPERATIONS_EXIT_VISIBLE} ${PLATFORM_OPERATIONS_EXIT_TOOLTIP}`
}
