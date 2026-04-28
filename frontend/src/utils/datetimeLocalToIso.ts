/**
 * Converts a `datetime-local` input value (no timezone suffix) to ISO-8601 UTC string.
 * Returns null if empty or not parseable (avoids sending Invalid Date to the API).
 */
export function datetimeLocalToIso(local: string): string | null {
  const t = local.trim()
  if (t === '') {
    return null
  }
  const d = new Date(t)
  if (Number.isNaN(d.getTime())) {
    return null
  }
  return d.toISOString()
}
