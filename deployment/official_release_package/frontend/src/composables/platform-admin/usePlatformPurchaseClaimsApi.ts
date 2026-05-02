import { isAxiosError } from 'axios'
import apiClient from '@/lib/apiClient'

export interface PlatformPurchaseClaimRow {
  id: number
  uuid: string
  company_id: number
  status: string
  platform_review_status?: string | null
  platform_review_notes?: string | null
  platform_reviewed_at?: string | null
  title?: string | null
  requested_amount?: string | number | null
  company?: { id: number; name: string; status?: string } | null
  creator?: { id: number; name: string } | null
  reviewer?: { id: number; name: string } | null
  platform_reviewer?: { id: number; name: string } | null
  purchases?: Array<{
    id: number
    reference_number?: string | null
    total?: number | string
    status?: string
    billing_flow_type?: string
  }>
}

export interface PaginatedPayload<T> {
  data: T[]
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
}

export async function fetchPlatformPurchaseClaims(params: {
  page?: number
  per_page?: number
  status?: string
  platform_review_status?: string
  company_id?: number
}): Promise<{ rows: PlatformPurchaseClaimRow[]; pagination: PaginatedPayload<PlatformPurchaseClaimRow> | null }> {
  const { data } = await apiClient.get<{ data: PaginatedPayload<PlatformPurchaseClaimRow> }>(
    '/platform/purchase-claims',
    { params },
  )
  const pag = data?.data
  return { rows: pag?.data ?? [], pagination: pag ?? null }
}

export async function reviewPlatformPurchaseClaim(
  id: number,
  payload: { status: 'approved' | 'rejected'; platform_review_notes?: string | null },
): Promise<PlatformPurchaseClaimRow> {
  const { data } = await apiClient.patch<{ data: PlatformPurchaseClaimRow }>(
    `/platform/purchase-claims/${id}/review`,
    payload,
  )
  return data.data
}

export function purchaseClaimsApiErrorMessage(e: unknown): string {
  if (isAxiosError(e) && e.response?.data && typeof e.response.data === 'object') {
    const m = (e.response.data as Record<string, unknown>).message
    return typeof m === 'string' ? m : 'request_failed'
  }
  return e instanceof Error ? e.message : 'request_failed'
}
