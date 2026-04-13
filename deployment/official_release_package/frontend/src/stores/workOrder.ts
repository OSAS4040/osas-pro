import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/lib/apiClient'

export interface WorkOrderItem {
  item_type: 'part' | 'labor' | 'service' | 'other'
  name: string
  product_id?: number
  quantity: number
  unit_price: number
  tax_rate?: number
}

export interface CreateWorkOrderPayload {
  customer_id: number
  vehicle_id: number
  assigned_technician_id?: number
  priority?: string
  customer_complaint?: string
  mileage_in?: number
  items: WorkOrderItem[]
}

export const useWorkOrderStore = defineStore('workOrder', () => {
  const orders  = ref<any[]>([])
  const current = ref<any | null>(null)
  const loading = ref(false)

  async function fetchOrders(params: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    try {
      const { data } = await apiClient.get('/work-orders', { params })
      orders.value = data.data.data
    } finally {
      loading.value = false
    }
  }

  async function fetchOrder(id: number): Promise<void> {
    const { data } = await apiClient.get(`/work-orders/${id}`)
    current.value = data.data
  }

  async function createOrder(payload: CreateWorkOrderPayload): Promise<any> {
    const { data } = await apiClient.post('/work-orders', payload)
    return data.data
  }

  async function updateStatus(id: number, status: string, extra: Record<string, unknown> = {}): Promise<any> {
    const { data } = await apiClient.patch(`/work-orders/${id}/status`, { status, ...extra })
    return data.data
  }

  return { orders, current, loading, fetchOrders, fetchOrder, createOrder, updateStatus }
})
