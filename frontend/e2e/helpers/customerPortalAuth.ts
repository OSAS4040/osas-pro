/** بيانات `DemoCompanySeeder`: العميل التجريبي */
export const DEFAULT_CUSTOMER_EMAIL = 'customer@demo.sa'
export const DEFAULT_CUSTOMER_PASSWORD = 'password'

export function resolveCustomerPortalCredentials(): { email: string; password: string } {
  const email =
    process.env.PW_CUSTOMER_PORTAL_EMAIL?.trim()
    || process.env.PW_CUSTOMER_EMAIL?.trim()
    || DEFAULT_CUSTOMER_EMAIL
  const password =
    process.env.PW_CUSTOMER_PORTAL_PASSWORD?.trim()
    || process.env.PW_CUSTOMER_PASSWORD?.trim()
    || DEFAULT_CUSTOMER_PASSWORD
  return { email, password }
}
