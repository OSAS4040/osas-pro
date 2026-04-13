<template>
  <div class="fleet-transfer">
    <div class="page-header">
      <h2>Transfer Balance to Vehicle Wallet</h2>
    </div>

    <form @submit.prevent="submit">
      <div class="form-group">
        <label>Vehicle</label>
        <select v-model="form.vehicle_id" required>
          <option value="" disabled>Select vehicle</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">
            {{ v.plate_number }} — {{ v.make }} {{ v.model }}
          </option>
        </select>
      </div>

      <div class="form-group">
        <label>Amount</label>
        <input v-model.number="form.amount" type="number" min="0.01" step="0.01" required />
      </div>

      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea v-model="form.notes" rows="2" />
      </div>

      <div v-if="error" class="error-msg">{{ error }}</div>
      <div v-if="success" class="success-msg">Transfer successful!</div>

      <div class="form-actions">
        <button type="button" class="btn btn-outline" @click="$emit('close')">Cancel</button>
        <button type="submit" class="btn btn-primary" :disabled="submitting">
          {{ submitting ? 'Processing...' : 'Confirm Transfer' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { http } from '@/api/http'
import { v4 as uuidv4 } from 'uuid'

const props = defineProps<{ customerId: number }>()
const emit = defineEmits<{ (e: 'close'): void; (e: 'success'): void }>()

interface Vehicle {
  id: number
  plate_number: string
  make: string
  model: string
}

const vehicles = ref<Vehicle[]>([])
const submitting = ref(false)
const error = ref('')
const success = ref(false)

const form = ref({
  vehicle_id: '',
  amount: 0,
  notes: '',
})

async function loadVehicles(): Promise<void> {
  const resp = await http.get(`/customers/${props.customerId}/vehicles`)
  vehicles.value = resp.data.data ?? []
}

async function submit(): Promise<void> {
  submitting.value = true
  error.value = ''
  success.value = false

  const idem = uuidv4()
  try {
    await http.post(
      '/wallet/transfer',
      {
        customer_id:     props.customerId,
        vehicle_id:      Number(form.value.vehicle_id),
        amount:          form.value.amount,
        notes:           form.value.notes || undefined,
        idempotency_key: idem,
      },
      { headers: { 'Idempotency-Key': idem, Accept: 'application/json' } },
    )
    success.value = true
    setTimeout(() => emit('success'), 800)
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    error.value = e.response?.data?.message ?? 'Transfer failed. Please try again.'
  } finally {
    submitting.value = false
  }
}

onMounted(loadVehicles)
</script>

<style scoped>
.fleet-transfer { padding: 1rem; }
.page-header { margin-bottom: 1rem; }
.form-group { margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.25rem; }
.form-group label { font-size: 0.875rem; font-weight: 600; color: #374151; }
.form-group input, .form-group select, .form-group textarea {
  padding: 0.5rem 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; font-size: 0.875rem;
}
.form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; }
.btn { padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; border: none; font-size: 0.875rem; }
.btn-primary { background: #3b82f6; color: #fff; }
.btn-outline { background: transparent; border: 1px solid #d1d5db; color: #374151; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.error-msg { color: #dc2626; font-size: 0.875rem; }
.success-msg { color: #16a34a; font-size: 0.875rem; }
</style>
