<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <RouterLink :to="{ name: 'ledger' }" class="text-sm text-blue-500 hover:underline mb-1 block">← العودة لدفتر الأستاذ</RouterLink>
        <h1 class="text-2xl font-bold text-gray-800">{{ entry?.entry_number }}</h1>
      </div>
      <button v-if="entry && !entry.reversed_by_entry_id" class="bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-lg text-sm hover:bg-red-100 transition"
              @click="showReversal = true"
      >
        إلغاء القيد (Reversal)
      </button>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">جارٍ التحميل...</div>

    <div v-else-if="entry" class="grid grid-cols-2 gap-6">
      <!-- Meta -->
      <div class="bg-white rounded-xl shadow p-5 space-y-3 col-span-2 lg:col-span-1">
        <h2 class="font-semibold text-gray-700 mb-3">بيانات القيد</h2>
        <dl class="grid grid-cols-2 gap-2 text-sm">
          <dt class="text-gray-500">النوع</dt>
          <dd class="font-medium">{{ typeLabel(entry.type) }}</dd>
          <dt class="text-gray-500">التاريخ</dt>
          <dd>{{ formatDate(entry.entry_date) }}</dd>
          <dt class="text-gray-500">إجمالي المدين</dt>
          <dd class="text-green-700 font-medium">{{ formatMoney(entry.total_debit) }}</dd>
          <dt class="text-gray-500">إجمالي الدائن</dt>
          <dd class="text-red-600 font-medium">{{ formatMoney(entry.total_credit) }}</dd>
          <dt class="text-gray-500">متوازن</dt>
          <dd>
            <span :class="Math.abs(entry.total_debit - entry.total_credit) < 0.01 ? 'text-green-600' : 'text-red-600'">
              {{ Math.abs(entry.total_debit - entry.total_credit) < 0.01 ? 'نعم ✓' : 'لا ✗' }}
            </span>
          </dd>
          <dt class="text-gray-500">الوصف</dt>
          <dd class="col-span-2">{{ entry.description }}</dd>
        </dl>
      </div>

      <!-- Reversed warning -->
      <div v-if="entry.reversed_by_entry_id" class="col-span-2 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">
        تم إلغاء هذا القيد. انظر قيد الإلغاء رقم: {{ entry.reversed_by_entry_id }}
      </div>

      <!-- Lines -->
      <div class="bg-white rounded-xl shadow col-span-2">
        <div class="p-4 border-b">
          <h2 class="font-semibold text-gray-700">سطور القيد</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-right">كود الحساب</th>
              <th class="px-4 py-3 text-right">اسم الحساب</th>
              <th class="px-4 py-3 text-right">النوع</th>
              <th class="px-4 py-3 text-right">المبلغ</th>
              <th class="px-4 py-3 text-right">الوصف</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="line in entry.lines" :key="line.id">
              <td class="px-4 py-3 font-mono text-blue-600">{{ line.account?.code }}</td>
              <td class="px-4 py-3">{{ line.account?.name }}</td>
              <td class="px-4 py-3">
                <span :class="line.type === 'debit' ? 'text-green-700' : 'text-red-600'" class="font-medium">
                  {{ line.type === 'debit' ? 'مدين' : 'دائن' }}
                </span>
              </td>
              <td class="px-4 py-3 font-medium">{{ formatMoney(line.amount) }}</td>
              <td class="px-4 py-3 text-gray-500">{{ line.description }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reversal Modal -->
    <div v-if="showReversal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl p-6 w-96 shadow-xl space-y-4">
        <h3 class="text-lg font-bold text-red-600">إلغاء القيد</h3>
        <p class="text-sm text-gray-600">سيتم إنشاء قيد عكسي كامل. هذا الإجراء لا يمكن التراجع عنه.</p>
        <textarea v-model="reversalReason" placeholder="سبب الإلغاء..." class="w-full border rounded-lg p-3 text-sm h-24 resize-none" />
        <div class="flex gap-3">
          <button :disabled="!reversalReason.trim()" class="flex-1 bg-red-600 text-white py-2 rounded-lg text-sm disabled:opacity-40" @click="submitReversal">
            تأكيد الإلغاء
          </button>
          <button class="flex-1 border py-2 rounded-lg text-sm" @click="showReversal = false">إلغاء</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const entry  = ref<any>(null)
const loading = ref(false)
const showReversal  = ref(false)
const reversalReason = ref('')

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`/ledger/${route.params.id}`)
    entry.value = data.data
  } finally {
    loading.value = false
  }
}

async function submitReversal() {
  await api.post(`/ledger/${entry.value.id}/reverse`, { reason: reversalReason.value })
  showReversal.value = false
  await load()
}

onMounted(load)

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }
function formatMoney(n: number) { return n ? Number(n).toLocaleString('ar-SA', { style: 'currency', currency: 'SAR' }) : '—' }
function typeLabel(t: string) {
  const map: Record<string, string> = { sale: 'مبيعات', purchase: 'مشتريات', payment: 'دفعة', reversal: 'إلغاء', adjustment: 'تسوية' }
  return map[t] ?? t
}
</script>
