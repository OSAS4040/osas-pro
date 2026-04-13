import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient, { withIdempotency } from '@/lib/apiClient'

export interface InvoiceItem {
  product_id?: number
  name: string
  quantity: number
  unit_price: number
  discount_amount?: number
  tax_rate?: number
}

export interface CreateInvoicePayload {
  customer_id?: number
  customer_type?: 'b2c' | 'b2b'
  type?: 'sale' | 'refund' | 'proforma'
  discount_amount?: number
  notes?: string
  items: InvoiceItem[]
  payment?: {
    method: string
    amount: number
    reference?: string
  }
}

export const useInvoiceStore = defineStore('invoice', () => {
  const invoices = ref<any[]>([])
  const current  = ref<any | null>(null)
  const loading  = ref(false)

  async function fetchInvoices(params: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    try {
      const { data } = await apiClient.get('/invoices', { params })
      invoices.value = data.data.data
    } finally {
      loading.value = false
    }
  }

  async function fetchInvoice(id: number): Promise<void> {
    const { data } = await apiClient.get(`/invoices/${id}`)
    current.value = data.data
  }

  async function createInvoice(payload: CreateInvoicePayload): Promise<any> {
    const config = withIdempotency()
    const { data } = await apiClient.post('/invoices', payload, config)
    return data.data
  }

  return { invoices, current, loading, fetchInvoices, fetchInvoice, createInvoice }
})
