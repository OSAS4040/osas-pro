<template>
  <div class="wallet-summary">
    <div class="page-header">
      <h1>Wallet Summary</h1>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else-if="wallets.length === 0" class="empty-state">
      No wallets found for this customer.
    </div>

    <div v-else class="wallets-grid">
      <div
        v-for="wallet in wallets"
        :key="wallet.id"
        class="wallet-card"
        :class="`wallet-card--${wallet.wallet_type}`"
      >
        <div class="wallet-card__header">
          <span class="wallet-type-badge">{{ walletTypeLabel(wallet.wallet_type) }}</span>
          <span class="wallet-status" :class="`status--${wallet.status}`">{{ wallet.status }}</span>
        </div>

        <div class="wallet-card__balance">
          <span class="balance-amount">{{ formatCurrency(wallet.balance, wallet.currency) }}</span>
        </div>

        <div v-if="wallet.vehicle_id" class="wallet-card__vehicle">
          Vehicle ID: {{ wallet.vehicle_id }}
        </div>

        <div class="wallet-card__actions">
          <button class="btn btn-sm btn-outline" @click="viewTransactions(wallet.id)">
            View Transactions
          </button>
        </div>
      </div>
    </div>

    <div class="wallet-actions">
      <button v-if="hasIndividualWallet" class="btn btn-primary" @click="showTopUp('individual')">
        Top Up (Individual)
      </button>
      <button v-if="hasFleetWallet" class="btn btn-primary" @click="showTopUp('fleet')">
        Top Up (Fleet)
      </button>
      <button v-if="hasFleetWallet" class="btn btn-secondary" @click="showTransfer = true">
        Transfer to Vehicle
      </button>
    </div>

    <TopUpModal
      v-if="topUpMode"
      :mode="topUpMode"
      :customer-id="customerId"
      @close="topUpMode = null"
      @success="onTopUpSuccess"
    />

    <FleetTransferModal
      v-if="showTransfer"
      :customer-id="customerId"
      @close="showTransfer = false"
      @success="onTransferSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useWalletStore } from '@/stores/walletStore'
import TopUpModal from '@/components/wallet/TopUpModal.vue'
import FleetTransferModal from '@/components/wallet/FleetTransferModal.vue'

const props = defineProps<{ customerId: number }>()
const router = useRouter()
const walletStore = useWalletStore()

const loading = ref(false)
const topUpMode = ref<'individual' | 'fleet' | null>(null)
const showTransfer = ref(false)

const wallets = computed(() => walletStore.wallets)

const hasIndividualWallet = computed(() =>
  wallets.value.some(w => w.wallet_type === 'customer_main')
)

const hasFleetWallet = computed(() =>
  wallets.value.some(w => w.wallet_type === 'fleet_main')
)

function walletTypeLabel(type: string): string {
  const labels: Record<string, string> = {
    customer_main: 'Individual Wallet',
    fleet_main: 'Fleet Main Wallet',
    vehicle_wallet: 'Vehicle Wallet',
  }
  return labels[type] ?? type
}

function formatCurrency(amount: number, currency: string): string {
  return new Intl.NumberFormat('ar-SA', { style: 'currency', currency }).format(amount)
}

function viewTransactions(walletId: number): void {
  router.push({ name: 'wallet-transactions', params: { walletId } })
}

function showTopUp(mode: 'individual' | 'fleet'): void {
  topUpMode.value = mode
}

async function onTopUpSuccess(): Promise<void> {
  topUpMode.value = null
  await loadWallets()
}

async function onTransferSuccess(): Promise<void> {
  showTransfer.value = false
  await loadWallets()
}

async function loadWallets(): Promise<void> {
  loading.value = true
  try {
    await walletStore.fetchSummary(props.customerId)
  } finally {
    loading.value = false
  }
}

onMounted(loadWallets)
</script>

<style scoped>
.wallet-summary { padding: 1.5rem; }
.page-header { margin-bottom: 1.5rem; }
.wallets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  margin-bottom: 1.5rem;
}
.wallet-card {
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  padding: 1.25rem;
  background: #fff;
}
.wallet-card--fleet_main { border-left: 4px solid #3b82f6; }
.wallet-card--vehicle_wallet { border-left: 4px solid #10b981; }
.wallet-card--customer_main { border-left: 4px solid #8b5cf6; }
.wallet-card__header { display: flex; justify-content: space-between; margin-bottom: 0.75rem; }
.wallet-type-badge { font-size: 0.75rem; font-weight: 600; color: #374151; }
.balance-amount { font-size: 1.5rem; font-weight: 700; color: #111827; }
.wallet-card__actions { margin-top: 1rem; }
.wallet-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.btn { padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; border: none; font-size: 0.875rem; }
.btn-primary { background: #3b82f6; color: #fff; }
.btn-secondary { background: #6b7280; color: #fff; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
.btn-sm { padding: 0.25rem 0.75rem; }
.status--active { color: #10b981; }
.status--suspended { color: #ef4444; }
.loading, .empty-state { color: #6b7280; padding: 2rem; text-align: center; }
</style>
