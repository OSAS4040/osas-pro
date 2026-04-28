/** مسار فعلي في التطبيق — لا يوجد مسار /admin/companies في الواجهة الحالية. */
export function platformCompanyPath(companyId: number): string {
  return `/platform/companies/${companyId}`
}
