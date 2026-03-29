<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <RouterLink to="/products" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </RouterLink>
        <h2 class="text-lg font-semibold text-gray-900">{{ isEdit ? 'تعديل منتج' : 'منتج جديد' }}</h2>
      </div>
    </div>

    <form @submit.prevent="save" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
      <div class="grid grid-cols-2 gap-5">

        <!-- الاسم -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">الاسم <span class="text-red-500">*</span></label>
          <input v-model="form.name" type="text" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

        <!-- الاسم بالعربي -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">الاسم بالعربي</label>
          <input v-model="form.name_ar" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

        <!-- نوع المنتج -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نوع المنتج</label>
          <select v-model="form.product_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="physical">مادي</option>
            <option value="service">خدمة</option>
            <option value="consumable">مستهلك</option>
            <option value="labor">عمالة</option>
          </select>
        </div>

        <!-- الوحدة -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">وحدة القياس</label>
          <select v-model="form.unit_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="">-- اختر وحدة --</option>
            <option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }} ({{ u.symbol }})</option>
          </select>
        </div>

        <!-- SKU -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">SKU</label>
          <input v-model="form.sku" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

        <!-- الباركود -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">الباركود</label>
          <input v-model="form.barcode" type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

        <!-- سعر البيع -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">سعر البيع <span class="text-red-500">*</span></label>
          <input v-model="form.sale_price" type="number" step="0.01" min="0" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

        <!-- سعر التكلفة -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">سعر التكلفة</label>
          <input v-model="form.cost_price" type="number" step="0.01" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

        <!-- نسبة الضريبة -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">نسبة الضريبة %</label>
          <input v-model="form.tax_rate" type="number" step="0.01" min="0" max="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
        </div>

      </div>

      <!-- خيارات -->
      <div class="flex gap-6 pt-2">
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
          <input v-model="form.is_taxable" type="checkbox" class="rounded" />
          خاضع للضريبة
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
          <input v-model="form.track_inventory" type="checkbox" class="rounded" />
          تتبع المخزون
        </label>
      </div>

      <!-- رسالة خطأ -->
      <p v-if="error" class="text-red-600 text-sm bg-red-50 px-4 py-2 rounded-lg">{{ error }}</p>

      <!-- أزرار -->
      <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
        <RouterLink to="/products" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">إلغاء</RouterLink>
        <button type="submit" :disabled="saving" class="px-6 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 font-medium">
          {{ saving ? 'جارٍ الحفظ...' : (isEdit ? 'حفظ التعديلات' : 'إنشاء المنتج') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'

const route  = useRoute()
const router = useRouter()

const isEdit = computed(() => !!route.params.id)
const saving = ref(false)
const error  = ref('')
const units  = ref<any[]>([])

const form = reactive({
  name: '', name_ar: '', product_type: 'physical',
  unit_id: '' as any, sku: '', barcode: '',
  sale_price: '' as any, cost_price: '' as any,
  tax_rate: 15 as any, is_taxable: true, track_inventory: true,
})

onMounted(async () => {
  const { data } = await apiClient.get('/units')
  units.value = data.data

  if (isEdit.value) {
    const { data: pd } = await apiClient.get(`/products/${route.params.id}`)
    const p = pd.data
    Object.assign(form, {
      name: p.name, name_ar: p.name_ar ?? '',
      product_type: p.product_type ?? 'physical',
      unit_id: p.unit_id ?? '',
      sku: p.sku ?? '', barcode: p.barcode ?? '',
      sale_price: p.sale_price ?? p.price,
      cost_price: p.cost_price ?? '',
      tax_rate: p.tax_rate ?? 15,
      is_taxable: p.is_taxable ?? true,
      track_inventory: p.track_inventory ?? true,
    })
  }
})

async function save() {
  saving.value = true
  error.value  = ''
  try {
    const payload = { ...form }
    if (!payload.unit_id) delete payload.unit_id
    if (!payload.cost_price) delete payload.cost_price

    if (isEdit.value) {
      await apiClient.put(`/products/${route.params.id}`, payload)
    } else {
      await apiClient.post('/products', payload)
    }
    router.push('/products')
  } catch (e: any) {
    const msg = e.response?.data?.message
    const errs = e.response?.data?.errors
    if (errs) {
      error.value = Object.values(errs).flat().join(' | ')
    } else {
      error.value = msg ?? 'فشل الحفظ.'
    }
  } finally {
    saving.value = false
  }
}
</script>
