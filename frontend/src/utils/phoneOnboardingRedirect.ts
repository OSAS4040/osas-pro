import type { RegistrationFlowState } from '@/stores/auth'

/**
 * Next route for phone OTP onboarding hub — must stay in sync with product rules (no step skipping).
 */
export function resolvePhoneOnboardingPath(
  flow: RegistrationFlowState | null | undefined,
  registrationStage: string | null | undefined,
  accountType: string | null | undefined,
): string {
  if (!flow?.onboarding_active) return '/'
  if (registrationStage === 'individual_completed') return '/phone/onboarding/done'
  if (flow.company_pending_review) return '/phone/onboarding/pending-review'
  if (flow.needs_account_type) return '/phone/onboarding/type'
  if (flow.needs_basic_profile && accountType === 'individual') return '/phone/onboarding/individual'
  if (flow.needs_basic_profile && accountType === 'company') return '/phone/onboarding/company'
  return '/phone/onboarding/type'
}
