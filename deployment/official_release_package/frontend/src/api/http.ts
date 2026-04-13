/**
 * Named re-export so views can use:
 *   import { http } from '@/api/http'
 *
 * The canonical source of truth is @/lib/apiClient.
 */
import apiClient, { withIdempotency } from '@/lib/apiClient'

export const http = apiClient
export { withIdempotency }
export default apiClient
