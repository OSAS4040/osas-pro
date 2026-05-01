import { describe, expect, it } from 'vitest'
import { resolvePhoneOnboardingPath } from './phoneOnboardingRedirect'

describe('resolvePhoneOnboardingPath', () => {
  it('sends inactive onboarding to home', () => {
    expect(
      resolvePhoneOnboardingPath(
        { onboarding_active: false, needs_account_type: false, needs_basic_profile: false, company_pending_review: false },
        null,
        null,
      ),
    ).toBe('/')
  })

  it('prioritizes individual completed over other flags', () => {
    expect(
      resolvePhoneOnboardingPath(
        {
          onboarding_active: true,
          needs_account_type: true,
          needs_basic_profile: true,
          company_pending_review: true,
        },
        'individual_completed',
        'individual',
      ),
    ).toBe('/phone/onboarding/done')
  })

  it('routes company pending review before account type', () => {
    expect(
      resolvePhoneOnboardingPath(
        {
          onboarding_active: true,
          needs_account_type: true,
          needs_basic_profile: false,
          company_pending_review: true,
        },
        'company_profile_submitted',
        'company',
      ),
    ).toBe('/phone/onboarding/pending-review')
  })

  it('requires account type when not pending and not completed', () => {
    expect(
      resolvePhoneOnboardingPath(
        {
          onboarding_active: true,
          needs_account_type: true,
          needs_basic_profile: true,
          company_pending_review: false,
        },
        'phone_verified',
        null,
      ),
    ).toBe('/phone/onboarding/type')
  })

  it('routes individual basic profile', () => {
    expect(
      resolvePhoneOnboardingPath(
        {
          onboarding_active: true,
          needs_account_type: false,
          needs_basic_profile: true,
          company_pending_review: false,
        },
        'account_type_selected',
        'individual',
      ),
    ).toBe('/phone/onboarding/individual')
  })

  it('routes company basic profile', () => {
    expect(
      resolvePhoneOnboardingPath(
        {
          onboarding_active: true,
          needs_account_type: false,
          needs_basic_profile: true,
          company_pending_review: false,
        },
        'account_type_selected',
        'company',
      ),
    ).toBe('/phone/onboarding/company')
  })

  it('defaults to account type when profile not needed', () => {
    expect(
      resolvePhoneOnboardingPath(
        {
          onboarding_active: true,
          needs_account_type: false,
          needs_basic_profile: false,
          company_pending_review: false,
        },
        'account_type_selected',
        'company',
      ),
    ).toBe('/phone/onboarding/type')
  })
})
