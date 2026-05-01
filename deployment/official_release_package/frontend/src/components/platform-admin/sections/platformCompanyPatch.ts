/** مفاتيح داخلية لـ emit «go-section» من جدول المشتركين — لا تُستخدم كأسماء مسارات. */

export const PLATFORM_COMPANY_PATCH_PREFIX = 'verdent:platform:company-patch:'

export function patchSectionKey(action: 'suspend' | 'reactivate', companyId: number): string {
  return `${PLATFORM_COMPANY_PATCH_PREFIX}${action}:${companyId}`
}

export function isPlatformCompanyPatchKey(key: string): boolean {
  return key.startsWith(PLATFORM_COMPANY_PATCH_PREFIX)
}

export function parsePlatformCompanyPatchKey(key: string): { action: 'suspend' | 'reactivate'; companyId: number } | null {
  if (!isPlatformCompanyPatchKey(key)) return null
  const rest = key.slice(PLATFORM_COMPANY_PATCH_PREFIX.length)
  const [action, idStr] = rest.split(':')
  if (action !== 'suspend' && action !== 'reactivate') return null
  const companyId = Number(idStr)
  if (!Number.isFinite(companyId)) return null
  return { action, companyId }
}
