import { describe, expect, it } from 'vitest'
import { isLedgerPostingFailure, LEDGER_POST_FAILED } from './apiErrorCodes'

describe('apiErrorCodes', () => {
  it('detects ledger posting failure payload', () => {
    expect(isLedgerPostingFailure({ code: LEDGER_POST_FAILED })).toBe(true)
    expect(isLedgerPostingFailure({ code: 'OTHER' })).toBe(false)
    expect(isLedgerPostingFailure(null)).toBe(false)
  })
})
