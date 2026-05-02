import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { pathToStaffNavKey } from '@/lib/staffNavKey'
import {
  applyWalletTopUpReviewerNavOverride,
  mergeStaffHiddenNavKeys,
  WALLET_TOP_UP_REQUESTS_NAV_KEY,
} from '@/config/staffProviderFocusNav'
import { mergeExecutionPartnerNavKeys } from '@/config/executionPartnerNav'

describe('applyWalletTopUpReviewerNavOverride', () => {
  const walletKey = pathToStaffNavKey('/wallet/top-up-requests')

  it('removes wallet hide when reviewer and not tenant-suppressed', () => {
    const merged = [walletKey, pathToStaffNavKey('/customers')]
    const out = applyWalletTopUpReviewerNavOverride(merged, [], (p) => p === 'wallet.top_up_requests.review')
    expect(out.includes(walletKey)).toBe(false)
    expect(out.length).toBe(1)
  })

  it('keeps wallet hidden when tenant policy lists the key', () => {
    const merged = [walletKey]
    const out = applyWalletTopUpReviewerNavOverride(merged, [walletKey], (p) => p === 'wallet.top_up_requests.review')
    expect(out.includes(walletKey)).toBe(true)
  })

  it('keeps hidden without wallet permission', () => {
    const merged = [walletKey]
    const out = applyWalletTopUpReviewerNavOverride(merged, [], () => false)
    expect(out.includes(walletKey)).toBe(true)
  })

  it('restores nav after execution-partner merge when reviewer', () => {
    const base = mergeStaffHiddenNavKeys([], ref('service_center'), ref(true), false)
    const withEp = mergeExecutionPartnerNavKeys(base, true)
    expect(withEp.includes(WALLET_TOP_UP_REQUESTS_NAV_KEY)).toBe(true)
    const final = applyWalletTopUpReviewerNavOverride(withEp, [], (p) =>
      ['wallet.top_up_requests.review', 'wallet.top_up_requests.view'].includes(p),
    )
    expect(final.includes(WALLET_TOP_UP_REQUESTS_NAV_KEY)).toBe(false)
  })
})
