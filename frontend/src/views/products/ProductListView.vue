<template>
  <div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white">المنتجات</h2>
      <div class="flex gap-2">
        <button class="px-3 py-2 text-sm border border-gray-300 dark:border-slate-600 dark:text-white rounded-lg hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-1.5" @click="downloadTemplate">
          <span>⬇</span> قالب Excel
        </button>
        <label class="px-3 py-2 text-sm border border-primary-500 text-primary-600 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors flex items-center gap-1.5 cursor-pointer">
          <span>⬆</span> استيراد Excel
          <input type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="importExcel" />
        </label>
        <RouterLink to="/products/new" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition-colors">
          + إضافة
        </RouterLink>
      </div>
    </div>
    <div v-if="importResult" class="rounded-lg p-3 text-sm" :class="importResult.error ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">
      {{ importResult.message }}
      <button class="mr-2 text-xs underline" @click="importResult = null">إغلاق</button>
    </div>

    <!-- فلاتر -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3">
      <div class="flex gap-3 flex-wrap">
        <input
          v-model="filters.search"
          type="text"
          placeholder="ابحث بالاسم، الباركود، SKU..."
          class="flex-1 min-w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
          @input="debouncedLoad"
        />
        <select v-model="filters.product_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="load">
          <option value="">كل الأنواع</option>
          <option value="physical">مادي</option>
          <option value="service">خدمة</option>
          <option value="consumable">مستهلك</option>
          <option value="labor">عمالة</option>
        </select>
        <select v-model="filters.is_active" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" @change="load">
          <option value="">كل الحالات</option>
          <option value="true">نشط</option>
          <option value="false">غير نشط</option>
        </select>
      </div>
    </div>

    <!-- جدول -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-400 text-sm">جارٍ التحميل...</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
          <tr>
            <th class="px-4 py-3 text-right">الاسم</th>
            <th class="px-4 py-3 text-right">النوع</th>
            <th class="px-4 py-3 text-right">الباركود</th>
            <th class="px-4 py-3 text-right">سعر البيع</th>
            <th class="px-4 py-3 text-right">الضريبة</th>
            <th class="px-4 py-3 text-right">المخزون</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3 text-center">إجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in products" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-900 text-right">
              {{ p.name }}
              <span v-if="p.name_ar" class="block text-xs text-gray-400">{{ p.name_ar }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <span :class="typeClass(p.product_type)" class="px-2 py-0.5 rounded-full text-xs">{{ typeLabel(p.product_type) }}</span>
            </td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs text-right">{{ p.barcode ?? '—' }}</td>
            <td class="px-4 py-3 text-right font-semibold">{{ Number(p.sale_price ?? p.price).toFixed(2) }} ر.س</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ p.tax_rate ?? 0 }}%</td>
            <td class="px-4 py-3 text-right">
              <span v-if="p.track_inventory" class="text-xs text-blue-600">متتبع</span>
              <span v-else class="text-xs text-gray-400">—</span>
            </td>
            <td class="px-4 py-3 text-right">
              <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs">
                {{ p.is_active ? 'نشط' : 'غير نشط' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <RouterLink :to="`/products/${p.id}/edit`" class="text-primary-600 hover:underline text-xs ml-3">تعديل</RouterLink>
              <button class="text-red-500 hover:text-red-700 text-xs" @click="deleteProduct(p)">حذف</button>
            </td>
          </tr>
          <tr v-if="!products.length">
            <td colspan="8" class="px-4 py-10 text-center text-gray-400">لا توجد منتجات.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- تصفح الصفحات -->
    <div v-if="pagination && pagination.last_page > 1" class="flex justify-between items-center text-sm text-gray-500">
      <span>{{ pagination.total }} منتج — صفحة {{ pagination.current_page }} من {{ pagination.last_page }}</span>
      <div class="flex gap-2">
        <button :disabled="pagination.current_page <= 1" class="px-3 py-1 border rounded-lg disabled:opacity-40" @click="page--; load()">السابق</button>
        <button :disabled="pagination.current_page >= pagination.last_page" class="px-3 py-1 border rounded-lg disabled:opacity-40" @click="page++; load()">التالي</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { appConfirm } from '@/services/appConfirmDialog'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const products    = ref<any[]>([])
const loading     = ref(false)
const page        = ref(1)
const pagination  = ref<any>(null)
const filters     = ref({ search: '', product_type: '', is_active: '' })
const importResult = ref<any>(null)

async function downloadTemplate() {
  const res = await apiClient.get('/products/template', { responseType: 'blob' })
  const url = URL.createObjectURL(res.data)
  const a   = document.createElement('a')
  a.href = url; a.download = 'products_template.csv'; a.click()
  URL.revokeObjectURL(url)
}

async function importExcel(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const fd = new FormData(); fd.append('file', file)
  try {
    const { data } = await apiClient.post('/products/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    importResult.value = { message: data.message ?? 'تم الاستيراد' }
    await load()
  } catch (err: any) {
    importResult.value = { error: true, message: err.response?.data?.message ?? 'فشل الاستيراد' }
  }
  (e.target as HTMLInputElement).value = ''
}

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 350)
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, any> = { page: page.value, per_page: 25 }
    if (filters.value.search)       params.search       = filters.value.search
    if (filters.value.product_type) params.product_type = filters.value.product_type
    if (filters.value.is_active)    params.is_active    = filters.value.is_active
    const { data } = await apiClient.get('/products', { params })
    const res = data.data
    products.value   = res.data ?? res
    pagination.value = res.current_page ? res : null
  } finally {
    loading.value = false
  }
}

async function deleteProduct(p: any) {
  const ok = await appConfirm({
    title: 'حذف المنتج',
    message: `هل تريد حذف المنتج «${p.name}»؟`,
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/products/${p.id}`)
    await load()
  } catch (e: any) {
    toast.error('فشل الحذف', e.response?.data?.message ?? '')
  }
}

function typeLabel(t: string): string {
  const m: Record<string, string> = { physical: 'مادي', service: 'خدمة', consumable: 'مستهلك', labor: 'عمالة' }
  return m[t] ?? t
}

function typeClass(t: string): string {
  const m: Record<string, string> = {
    physical: 'bg-blue-100 text-blue-700', service: 'bg-primary-100 text-primary-700',
    consumable: 'bg-orange-100 text-orange-700', labor: 'bg-gray-100 text-gray-600',
  }
  return m[t] ?? 'bg-gray-100 text-gray-600'
}

onMounted(load)
</script>
