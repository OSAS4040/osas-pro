import { defineStore } from 'pinia'
import { ref } from 'vue'
import { http } from '@/api/http'

export interface WalletSummary {
  id: number
  wallet_type: 'customer_main' | 'fleet_main' | 'vehicle_wallet'
  vehicle_id: number | null
  balance: number
  status: string
  currency: string
}

export const useWalletStore = defineStore('wallet', () => {
  const wallets = ref<WalletSummary[]>([])
  const loading = ref(false)

  async function fetchSummary(customerId: number): Promise<void> {
    loading.value = true
    try {
      const resp = await http.get(`/wallet/${customerId}/summary`)
      wallets.value = resp.data.data ?? []
    } finally {
      loading.value = false
    }
  }

  function reset(): void {
    wallets.value = []
  }

  return { wallets, loading, fetchSummary, reset }
})
