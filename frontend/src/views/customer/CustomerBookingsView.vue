<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-bold text-gray-900">حجز موعد</h2>
      <p class="text-xs text-gray-400">احجز موعد صيانة لسيارتك</p>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 p-6 space-y-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">المركبة</label>
        <select v-model="form.vehicle_id" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:outline-none">
          <option value="">اختر المركبة</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">الخدمة المطلوبة</label>
        <input v-model="form.description" type="text" placeholder="مثال: تغيير زيت، فحص شامل..." class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:outline-none" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">التاريخ المفضل</label>
        <input v-model="form.preferred_date" type="date" :min="minDate" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:outline-none" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">ملاحظات إضافية</label>
        <textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-orange-400 focus:outline-none" placeholder="أي ملاحظات إضافية..."></textarea>
      </div>

      <p v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</p>
      <p v-if="success" class="text-sm text-green-700 bg-green-50 px-3 py-2 rounded-lg">{{ success }}</p>

      <button @click="submit" :disabled="loading"
        class="w-full bg-orange-500 text-white py-3 rounded-xl text-sm font-semibold hover:bg-orange-600 transition-colors disabled:opacity-50">
        {{ loading ? 'جارٍ الإرسال...' : 'تأكيد الحجز' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'

const loading  = ref(false)
const vehicles = ref<any[]>([])
const error    = ref('')
const success  = ref('')

const form = ref({ vehicle_id: '', description: '', preferred_date: '', notes: '' })
const minDate = computed(() => new Date(Date.now() + 86400000).toISOString().split('T')[0])

async function load() {
  try {
    const { data } = await apiClient.get('/vehicles', { params: { per_page: 50 } })
    vehicles.value = data.data ?? []
  } catch { /* silent */ }
}

async function submit() {
  if (!form.value.vehicle_id || !form.value.description) {
    error.value = 'يرجى اختيار المركبة وتحديد الخدمة المطلوبة'
    return
  }
  loading.value = true
  error.value   = ''
  try {
    await apiClient.post('/work-orders', {
      vehicle_id:  form.value.vehicle_id,
      description: form.value.description,
      notes:       form.value.notes,
    })
    success.value = 'تم إرسال طلبك بنجاح! سيتواصل معك فريقنا لتأكيد الموعد.'
    form.value = { vehicle_id: '', description: '', preferred_date: '', notes: '' }
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'فشل إرسال الطلب'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
