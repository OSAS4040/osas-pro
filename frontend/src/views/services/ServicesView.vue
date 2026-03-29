<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">Services</h2>
      <button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700" @click="openCreate">
        + Add Service
      </button>
    </div>

    <div class="flex gap-3">
      <input
        v-model="search"
        type="text"
        placeholder="Search name or code..."
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64"
        @input="debouncedLoad"
      />
      <label class="flex items-center gap-2 text-sm">
        <input v-model="activeOnly" type="checkbox" class="rounded" @change="load" />
        Active only
      </label>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Code</th>
            <th class="px-4 py-3 text-right">Base Price</th>
            <th class="px-4 py-3 text-right">Tax %</th>
            <th class="px-4 py-3 text-right">Est. Min</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="loading">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">Loading...</td>
          </tr>
          <tr v-for="svc in services" :key="svc.id">
            <td class="px-4 py-3 font-medium">{{ svc.name }}</td>
            <td class="px-4 py-3 text-gray-500">{{ svc.code ?? '—' }}</td>
            <td class="px-4 py-3 text-right">{{ formatPrice(svc.base_price) }}</td>
            <td class="px-4 py-3 text-right">{{ svc.tax_rate }}%</td>
            <td class="px-4 py-3 text-right">{{ svc.estimated_minutes ?? '—' }}</td>
            <td class="px-4 py-3">
              <span :class="svc.is_active ? 'text-green-600' : 'text-gray-400'">
                {{ svc.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right space-x-2">
              <button class="text-blue-600 hover:text-blue-800 text-xs" @click="openEdit(svc)">Edit</button>
              <button class="text-red-500 hover:text-red-700 text-xs" @click="deleteService(svc)">Delete</button>
            </td>
          </tr>
          <tr v-if="!loading && !services.length">
            <td colspan="7" class="px-4 py-8 text-center text-gray-400">No services found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-lg">
        <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit Service' : 'Create Service' }}</h3>
        <form class="space-y-4" @submit.prevent="submit">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
              <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
              <input v-model="form.code" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Base Price *</label>
              <input v-model="form.base_price" type="number" min="0" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tax Rate (%)</label>
              <input v-model="form.tax_rate" type="number" min="0" max="100" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Est. Minutes</label>
              <input v-model="form.estimated_minutes" type="number" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="flex items-center gap-2 pt-6">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded" />
              <label for="is_active" class="text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
          </div>
          <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" class="px-4 py-2 text-sm border border-gray-300 rounded-lg" @click="showModal = false">Cancel</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg disabled:opacity-50">
              {{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'

interface ServiceItem {
  id: number
  name: string
  code: string | null
  base_price: string
  tax_rate: string
  estimated_minutes: number | null
  is_active: boolean
}

const services = ref<ServiceItem[]>([])
const loading  = ref(false)
const showModal = ref(false)
const saving   = ref(false)
const error    = ref('')
const editing  = ref<ServiceItem | null>(null)
const search   = ref('')
const activeOnly = ref(false)

const form = ref({
  name: '', code: '', base_price: 0, tax_rate: 15,
  estimated_minutes: null as number | null,
  description: '', is_active: true,
})

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(load, 350)
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, any> = {}
    if (search.value) params.search = search.value
    if (activeOnly.value) params.is_active = 1
    const { data } = await apiClient.get('/services', { params })
    services.value = data.data.data ?? data.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', code: '', base_price: 0, tax_rate: 15, estimated_minutes: null, description: '', is_active: true }
  error.value = ''
  showModal.value = true
}

function openEdit(svc: ServiceItem) {
  editing.value = svc
  form.value = {
    name: svc.name, code: svc.code ?? '', base_price: Number(svc.base_price),
    tax_rate: Number(svc.tax_rate), estimated_minutes: svc.estimated_minutes,
    description: '', is_active: svc.is_active,
  }
  error.value = ''
  showModal.value = true
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await apiClient.put(`/services/${editing.value.id}`, form.value)
    } else {
      await apiClient.post('/services', form.value)
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Failed to save service.'
  } finally {
    saving.value = false
  }
}

async function deleteService(svc: ServiceItem) {
  if (!confirm(`Delete service "${svc.name}"?`)) return
  try {
    await apiClient.delete(`/services/${svc.id}`)
    await load()
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Failed to delete service.')
  }
}

function formatPrice(price: string): string {
  return Number(price).toFixed(2)
}

onMounted(load)
</script>
