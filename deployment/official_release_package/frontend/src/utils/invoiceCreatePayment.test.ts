import { describe, expect, it } from 'vitest'
import {
  getDefaultPaidAmountByPaymentMethod,
  isImmediateInvoicePaymentMethod,
  remainingFromTotalAndPaid,
  roundMoney2,
  validatePaidForSubmit,
} from './invoiceCreatePayment'

describe('invoiceCreatePayment', () => {
  it('roundMoney2', () => {
    expect(roundMoney2(10.005)).toBe(10.01)
    expect(roundMoney2(10.004)).toBe(10)
  })

  it('isImmediateInvoicePaymentMethod', () => {
    expect(isImmediateInvoicePaymentMethod('card')).toBe(true)
    expect(isImmediateInvoicePaymentMethod('credit')).toBe(false)
  })

  it('getDefaultPaidAmountByPaymentMethod', () => {
    expect(getDefaultPaidAmountByPaymentMethod('card', 100.5)).toBe(100.5)
    expect(getDefaultPaidAmountByPaymentMethod('cash', 0)).toBe(0)
    expect(getDefaultPaidAmountByPaymentMethod('credit', 200)).toBe(0)
  })

  it('remainingFromTotalAndPaid', () => {
    expect(remainingFromTotalAndPaid(100, 100)).toBe(0)
    expect(remainingFromTotalAndPaid(100, 30)).toBe(70)
  })

  it('validatePaidForSubmit', () => {
    expect(validatePaidForSubmit({ method: 'card', paid: 50, invoiceTotal: 100 }).ok).toBe(true)
    expect(validatePaidForSubmit({ method: 'card', paid: 100, invoiceTotal: 100 }).ok).toBe(true)
    const over = validatePaidForSubmit({ method: 'cash', paid: 101, invoiceTotal: 100 })
    expect(over.ok).toBe(false)
    if (!over.ok) expect(over.issue).toBe('over_total')
    const cred = validatePaidForSubmit({ method: 'credit', paid: 10, invoiceTotal: 100 })
    expect(cred.ok).toBe(false)
    if (!cred.ok) expect(cred.issue).toBe('credit_partial_not_supported')
  })
})
