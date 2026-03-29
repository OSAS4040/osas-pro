import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/lib/apiClient'

export interface Vehicle {
  id: number
  uuid: string
  plate_number: string
  make: string
  model: string
  year: number | null
  color: string | null
  fuel_type: string | null
  transmission: string | null
  mileage_in: number | null
  customer_id: number
  is_active: boolean
  customer?: { id: number; name: string }
}

export const useVehicleStore = defineStore('vehicle', () => {
  const vehicles = ref<Vehicle[]>([])
  const current  = ref<Vehicle | null>(null)
  const loading  = ref(false)

  async function fetchVehicles(params: Record<string, unknown> = {}): Promise<void> {
    loading.value = true
    try {
      const { data } = await apiClient.get('/vehicles', { params })
      vehicles.value = data.data.data ?? data.data ?? []
    } finally {
      loading.value = false
    }
  }

  async function fetchVehicle(id: number): Promise<void> {
    const { data } = await apiClient.get(`/vehicles/${id}`)
    current.value = data.data
  }

  async function createVehicle(payload: Partial<Vehicle>): Promise<Vehicle> {
    const { data } = await apiClient.post('/vehicles', payload)
    return data.data
  }

  return { vehicles, current, loading, fetchVehicles, fetchVehicle, createVehicle }
})
