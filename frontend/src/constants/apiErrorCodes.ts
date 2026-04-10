/** Backend `code` field — see App\Exceptions\LedgerPostingFailedException::ERROR_CODE */
export const LEDGER_POST_FAILED = 'LEDGER_POST_FAILED'

export function isLedgerPostingFailure(payload: unknown): boolean {
  if (!payload || typeof payload !== 'object') return false
  const code = (payload as { code?: unknown }).code
  return code === LEDGER_POST_FAILED
}
