import { isAxiosError } from 'axios'
import apiClient from '@/lib/apiClient'

export interface PaginatedPayload<T> {
  data: T[]
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
}

export interface PlatformPricingRequestRow {
  id: number
  uuid: string
  company_id: number
  customer_id: number
  status: string
  title?: string | null
  created_at?: string
  lines?: unknown[]
}

export async function fetchPricingRequests(params: {
  page?: number
  per_page?: number
  status?: string
  company_id?: number
}): Promise<{ rows: PlatformPricingRequestRow[]; pagination: PaginatedPayload<PlatformPricingRequestRow> | null }> {
  const { data } = await apiClient.get<{ data: PaginatedPayload<PlatformPricingRequestRow> }>('/platform/pricing/requests', {
    params,
  })
  const pag = data?.data
  return { rows: pag?.data ?? [], pagination: pag ?? null }
}

export async function fetchPricingRequestDetail(uuid: string): Promise<Record<string, unknown>> {
  const { data } = await apiClient.get<{ data: Record<string, unknown> }>(`/platform/pricing/requests/${uuid}`)
  return (data?.data ?? {}) as Record<string, unknown>
}

export async function postPricingAction(
  path: string,
  body?: Record<string, unknown>,
): Promise<Record<string, unknown>> {
  const { data } = await apiClient.post<{ data: Record<string, unknown> }>(path, body ?? {})
  return (data?.data ?? {}) as Record<string, unknown>
}

export function pricingApiErrorMessage(e: unknown): string {
  if (isAxiosError(e) && e.response?.data && typeof e.response.data === 'object') {
    const m = (e.response.data as Record<string, unknown>).message
    return typeof m === 'string' ? m : 'request_failed'
  }
  return e instanceof Error ? e.message : 'request_failed'
}

export async function fetchCustomerPriceVersions(params: {
  company_id: number
  customer_id: number
  page?: number
}): Promise<{ rows: Record<string, unknown>[]; pagination: PaginatedPayload<Record<string, unknown>> | null }> {
  const { data } = await apiClient.get<{ data: PaginatedPayload<Record<string, unknown>> }>(
    '/platform/pricing/customer-price-versions',
    { params },
  )
  const pag = data?.data
  return { rows: pag?.data ?? [], pagination: pag ?? null }
}
