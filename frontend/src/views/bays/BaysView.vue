<template>
  <div class="space-y-6" dir="rtl">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h2 class="text-2xl font-bold text-gray-900">مناطق العمل</h2>
      <button class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
              @click="showModal = true"
      >
        <PlusIcon class="w-4 h-4" />
        منطقة عمل جديدة
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
      <div v-for="s in statusCounts" :key="s.status"
           class="bg-white rounded-xl p-3 border border-gray-200 text-center cursor-pointer hover:border-primary-300"
           @click="filterStatus = s.status === filterStatus ? '' : s.status"
      >
        <p class="text-xl font-bold" :class="s.color">{{ s.count }}</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ s.label }}</p>
        <div :class="s.dot" class="w-2 h-2 rounded-full mx-auto mt-1"></div>
      </div>
    </div>

    <!-- Bay Cards -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
      <div v-for="bay in filtered" :key="bay.id"
           class="bg-white rounded-xl border-2 transition-colors p-4 shadow-sm"
           :class="statusBorder(bay.status)"
      >
        <div class="flex items-start justify-between mb-3">
          <div>
            <p class="font-bold text-gray-900">{{ bay.name }}</p>
            <p class="text-xs text-gray-400">{{ bay.code }} · {{ bay.type }}</p>
          </div>
          <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="statusBadge(bay.status)">
            {{ statusLabel(bay.status) }}
          </span>
        </div>
        <div v-if="bay.capabilities?.length" class="flex flex-wrap gap-1 mb-3">
          <span v-for="cap in bay.capabilities" :key="cap"
                class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full text-xs"
          >{{ cap }}</span>
        </div>
        <div class="flex gap-2 flex-wrap">
          <button v-for="opt in statusOptions(bay.status)" :key="opt.value"
                  class="flex-1 text-xs py-1.5 rounded-lg font-medium transition-colors"
                  :class="opt.class"
                  @click="changeStatus(bay, opt.value)"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>
      <div v-if="!filtered.length" class="col-span-full text-center py-10 text-gray-400">لا توجد مناطق عمل</div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-bold text-lg">منطقة عمل جديدة</h3>
          <button @click="showModal = false"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
        </div>
        <form class="p-6 space-y-4" @submit.prevent="save">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الكود *</label>
              <input v-model="form.code" required placeholder="L01" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الاسم *</label>
              <input v-model="form.name" required placeholder="منطقة عمل 1" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">النوع</label>
              <select v-model="form.type" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none">
                <option value="lift">منطقة عمل</option>
                <option value="bay">منفذ</option>
                <option value="wash">غسيل</option>
                <option value="alignment">ضبط</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">السعة</label>
              <input v-model.number="form.capacity" type="number" min="1" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">القدرات (مفصولة بفاصلة)</label>
            <input v-model="capStr" placeholder="oil_change, brakes, alignment"
                   class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div v-if="modalError" class="text-red-600 text-sm bg-red-50 rounded-lg p-3">{{ modalError }}</div>
          <div class="flex gap-3 justify-end">
            <button type="button" class="px-4 py-2 border rounded-lg text-sm text-gray-700" @click="showModal = false">إلغاء</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium disabled:opacity-50">
              {{ saving ? 'جاري...' : 'إنشاء' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'


const bays = ref<any[]>([])
const loading = ref(true)
const filterStatus = ref('')
const showModal = ref(false)
const saving = ref(false)
const modalError = ref('')
const capStr = ref('')
const form = ref({ code: '', name: '', type: 'lift', capacity: 1 })

const filtered = computed(() =>
  bays.value.filter(b => !filterStatus.value || b.status === filterStatus.value)
)

const statusCounts = computed(() => [
  { status: 'available',   label: 'متاحة',       color: 'text-green-600',  dot: 'bg-green-500' },
  { status: 'reserved',    label: 'محجوزة',      color: 'text-blue-600',   dot: 'bg-blue-500' },
  { status: 'in_use',      label: 'مستخدمة',     color: 'text-orange-500', dot: 'bg-orange-500' },
  { status: 'maintenance', label: 'صيانة',       color: 'text-red-600',    dot: 'bg-red-500' },
  { status: '',            label: 'الكل',         color: 'text-gray-700',   dot: 'bg-gray-400' },
].map(s => ({ ...s, count: s.status ? bays.value.filter(b => b.status === s.status).length : bays.value.length }))
)

function statusLabel(s: string) {
  return { available: 'متاح', reserved: 'محجوز', in_use: 'مستخدم', maintenance: 'صيانة', out_of_service: 'خارج الخدمة' }[s] ?? s
}

function statusBorder(s: string) {
  return { available: 'border-green-300', reserved: 'border-blue-300', in_use: 'border-orange-300', maintenance: 'border-red-300' }[s] ?? 'border-gray-200'
}

function statusBadge(s: string) {
  return { available: 'bg-green-100 text-green-700', reserved: 'bg-blue-100 text-blue-700', in_use: 'bg-orange-100 text-orange-700', maintenance: 'bg-red-100 text-red-700' }[s] ?? 'bg-gray-100 text-gray-700'
}

function statusOptions(current: string) {
  const all = [
    { value: 'available',   label: 'تحرير',       class: 'bg-green-100 text-green-700 hover:bg-green-200' },
    { value: 'in_use',      label: 'استخدام',     class: 'bg-orange-100 text-orange-700 hover:bg-orange-200' },
    { value: 'maintenance', label: 'صيانة',       class: 'bg-red-100 text-red-700 hover:bg-red-200' },
  ]
  return all.filter(o => o.value !== current)
}

async function changeStatus(bay: any, status: string) {
  await apiClient.patch(`/bays/${bay.id}/status`, { status })
  bay.status = status
}

async function load() {
  loading.value = true
  try {
    const r = await apiClient.get('/bays')
    bays.value = r.data?.data ?? []
  } finally { loading.value = false }
}

async function save() {
  saving.value = true; modalError.value = ''
  try {
    const payload = {
      ...form.value,
      capabilities: capStr.value.split(',').map(s => s.trim()).filter(Boolean),
    }
    await apiClient.post('/bays', payload)
    await load()
    showModal.value = false
    form.value = { code: '', name: '', type: 'lift', capacity: 1 }
    capStr.value = ''
  } catch (e: any) {
    modalError.value = e?.response?.data?.message ?? 'حدث خطأ'
  } finally { saving.value = false }
}

onMounted(load)
</script>
