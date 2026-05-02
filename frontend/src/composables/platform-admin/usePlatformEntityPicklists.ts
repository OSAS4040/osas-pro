import apiClient from '@/lib/apiClient'

export interface PlatformCompanyOption {
  id: number
  name: string
}

export interface PlatformCustomerOption {
  id: number
  name: string
  company_id: number
  company_name: string
}

export async function fetchPlatformCompaniesOptions(params?: {
  per_page?: number
  search?: string
}): Promise<PlatformCompanyOption[]> {
  const per_page = Math.min(100, Math.max(10, params?.per_page ?? 100))
  const { data } = await apiClient.get<{
    data: Array<{ id: number; name: string }>
  }>('/platform/companies', {
    params: {
      per_page,
      ...(params?.search ? { search: params.search } : {}),
    },
  })
  const rows = Array.isArray(data?.data) ? data.data : []
  return rows.map((r) => ({ id: r.id, name: r.name }))
}

export async function fetchPlatformCustomersOptions(params?: {
  company_id?: number
  per_page?: number
}): Promise<PlatformCustomerOption[]> {
  const per_page = Math.min(100, Math.max(1, params?.per_page ?? 100))
  const { data } = await apiClient.get<{
    data: PlatformCustomerOption[]
  }>('/platform/customers', {
    params: {
      per_page,
      status: 'all',
      ...(params?.company_id ? { company_id: params.company_id } : {}),
    },
  })
  return Array.isArray(data?.data) ? data.data : []
}
