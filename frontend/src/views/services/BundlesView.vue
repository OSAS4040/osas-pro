<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold text-gray-900">Bundles</h2>
      <button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700" @click="openCreate">
        + New Bundle
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-if="loading" class="col-span-3 py-12 text-center text-gray-400">Loading...</div>

      <div
        v-for="bundle in bundles"
        :key="bundle.id"
        class="bg-white rounded-xl border border-gray-200 p-4 space-y-3"
      >
        <div class="flex items-start justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">{{ bundle.name }}</h3>
            <p v-if="bundle.code" class="text-xs text-gray-400">{{ bundle.code }}</p>
          </div>
          <span :class="bundle.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
            class="text-xs px-2 py-0.5 rounded-full">
            {{ bundle.is_active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <div class="space-y-1">
          <div v-for="item in bundle.items" :key="item.id" class="flex justify-between text-sm text-gray-600">
            <span>{{ item.service?.name ?? item.product?.name ?? '—' }}</span>
            <span class="text-gray-400">× {{ item.quantity }}</span>
          </div>
        </div>

        <div class="flex justify-between items-center pt-2 border-t border-gray-100">
          <span class="text-sm font-semibold text-gray-900">
            {{ formatPrice(bundle.base_price) }} SAR
          </span>
          <div class="flex gap-2">
            <button class="text-blue-600 text-xs hover:underline" @click="viewBundle(bundle)">View</button>
            <button class="text-red-500 text-xs hover:underline" @click="deleteBundle(bundle)">Delete</button>
          </div>
        </div>
      </div>

      <div v-if="!loading && !bundles.length" class="col-span-3 py-12 text-center text-gray-400">
        No bundles yet.
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 overflow-y-auto p-4">
      <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-2xl">
        <h3 class="font-semibold text-gray-900 mb-4">{{ editing ? 'Edit Bundle' : 'Create Bundle' }}</h3>
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
              <label class="block text-sm font-medium text-gray-700 mb-1">Base Price</label>
              <input v-model="form.base_price" type="number" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="flex items-center gap-4 pt-5">
              <label class="flex items-center gap-2 text-sm">
                <input v-model="form.override_item_prices" type="checkbox" class="rounded" />
                Override item prices
              </label>
              <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_active" type="checkbox" class="rounded" />
                Active
              </label>
            </div>
          </div>

          <div>
            <div class="flex justify-between mb-2">
              <label class="text-sm font-medium text-gray-700">Bundle Items</label>
              <button type="button" class="text-xs text-blue-600 hover:underline" @click="addItem">+ Add Item</button>
            </div>
            <div class="space-y-2">
              <div v-for="(item, i) in form.items" :key="i" class="flex gap-2 items-center">
                <select v-model="item.item_type" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
                  <option value="service">Service</option>
                  <option value="product">Product</option>
                </select>
                <select v-if="item.item_type === 'service'" v-model="item.service_id" class="border border-gray-300 rounded px-2 py-1.5 text-sm flex-1">
                  <option value="">Select service</option>
                  <option v-for="s in serviceOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-else v-model="item.product_id" class="border border-gray-300 rounded px-2 py-1.5 text-sm flex-1">
                  <option value="">Select product</option>
                  <option v-for="p in productOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <input v-model="item.quantity" type="number" min="0.001" step="0.001" placeholder="Qty" class="border border-gray-300 rounded px-2 py-1.5 text-sm w-20" />
                <button type="button" class="text-red-400 hover:text-red-600 text-sm" @click="form.items.splice(i, 1)">✕</button>
              </div>
            </div>
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

interface BundleItem {
  id: number
  item_type: string
  service?: { name: string } | null
  product?: { name: string } | null
  quantity: string
}

interface Bundle {
  id: number
  name: string
  code: string | null
  base_price: string
  is_active: boolean
  items: BundleItem[]
}

const bundles   = ref<Bundle[]>([])
const loading   = ref(false)
const showModal = ref(false)
const saving    = ref(false)
const error     = ref('')
const editing   = ref<Bundle | null>(null)

const serviceOptions = ref<{ id: number; name: string }[]>([])
const productOptions = ref<{ id: number; name: string }[]>([])

const form = ref({
  name: '', code: '', base_price: 0,
  override_item_prices: false, is_active: true,
  items: [] as Array<{
    item_type: string
    service_id: number | null
    product_id: number | null
    quantity: number
  }>,
})

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get('/bundles')
    bundles.value = data.data.data ?? data.data
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  const [s, p] = await Promise.all([
    apiClient.get('/services', { params: { is_active: 1, per_page: 200 } }),
    apiClient.get('/products', { params: { is_active: 1, per_page: 200 } }),
  ])
  serviceOptions.value = (s.data.data.data ?? s.data.data) ?? []
  productOptions.value = (p.data.data.data ?? p.data.data) ?? []
}

function openCreate() {
  editing.value = null
  form.value = { name: '', code: '', base_price: 0, override_item_prices: false, is_active: true, items: [] }
  error.value = ''
  showModal.value = true
  loadOptions()
}

function viewBundle(bundle: Bundle) {
  editing.value = bundle
  form.value = {
    name: bundle.name, code: bundle.code ?? '',
    base_price: Number(bundle.base_price),
    override_item_prices: false, is_active: bundle.is_active,
    items: bundle.items.map(i => ({
      item_type: i.item_type,
      service_id: i.item_type === 'service' ? (i as any).service_id : null,
      product_id: i.item_type === 'product' ? (i as any).product_id : null,
      quantity: Number(i.quantity),
    })),
  }
  error.value = ''
  showModal.value = true
  loadOptions()
}

function addItem() {
  form.value.items.push({ item_type: 'service', service_id: null, product_id: null, quantity: 1 })
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await apiClient.put(`/bundles/${editing.value.id}`, form.value)
    } else {
      await apiClient.post('/bundles', form.value)
    }
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Failed to save bundle.'
  } finally {
    saving.value = false
  }
}

async function deleteBundle(bundle: Bundle) {
  if (!confirm(`Delete bundle "${bundle.name}"?`)) return
  try {
    await apiClient.delete(`/bundles/${bundle.id}`)
    await load()
  } catch (e: any) {
    alert(e.response?.data?.message ?? 'Failed to delete bundle.')
  }
}

function formatPrice(price: string): string {
  return Number(price).toFixed(2)
}

onMounted(load)
</script>
