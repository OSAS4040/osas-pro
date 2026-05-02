import { isAxiosError } from 'axios'
import apiClient from '@/lib/apiClient'

export interface PlatformServiceProviderRow {
  id: number
  uuid: string
  name: string
  contact_name?: string | null
  phone?: string | null
  email?: string | null
  is_active: boolean
  regions?: unknown
}

export interface PaginatedPayload<T> {
  data: T[]
  current_page?: number
  last_page?: number
  per_page?: number
  total?: number
}

export async function fetchPlatformProviders(params: {
  page?: number
  per_page?: number
  active_only?: boolean
}): Promise<{ rows: PlatformServiceProviderRow[]; pagination: PaginatedPayload<PlatformServiceProviderRow> | null }> {
  const { data } = await apiClient.get<{ data: PaginatedPayload<PlatformServiceProviderRow> }>('/platform/providers', {
    params,
  })
  const pag = data?.data
  return { rows: pag?.data ?? [], pagination: pag ?? null }
}

export async function createPlatformProvider(payload: {
  name: string
  contact_name?: string
  phone?: string
  email?: string
  regions?: unknown
  notes?: string
}): Promise<PlatformServiceProviderRow> {
  const { data } = await apiClient.post<{ data: PlatformServiceProviderRow }>('/platform/providers', payload)
  return data.data
}

export async function fetchProviderCosts(
  providerId: number,
  params?: { page?: number; per_page?: number },
): Promise<{ rows: Record<string, unknown>[]; pagination: PaginatedPayload<Record<string, unknown>> | null }> {
  const { data } = await apiClient.get<{ data: PaginatedPayload<Record<string, unknown>> }>(
    `/platform/providers/${providerId}/costs`,
    { params },
  )
  const pag = data?.data
  return { rows: pag?.data ?? [], pagination: pag ?? null }
}

export async function createProviderCost(
  providerId: number,
  payload: {
    service_code: string
    cost_amount: number
    currency?: string
    effective_from?: string
    notes?: string
  },
): Promise<Record<string, unknown>> {
  const { data } = await apiClient.post<{ data: Record<string, unknown> }>(
    `/platform/providers/${providerId}/costs`,
    payload,
  )
  return data.data
}

export function providersApiErrorMessage(e: unknown): string {
  if (isAxiosError(e) && e.response?.data && typeof e.response.data === 'object') {
    const m = (e.response.data as Record<string, unknown>).message
    return typeof m === 'string' ? m : 'request_failed'
  }
  return e instanceof Error ? e.message : 'request_failed'
}
