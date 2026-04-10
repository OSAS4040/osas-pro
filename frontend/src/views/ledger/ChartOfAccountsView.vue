<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">دليل الحسابات</h1>
    </div>

    <!-- Trial Balance -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-sm">
      <p class="font-semibold text-indigo-700 mb-2">ميزان المراجعة</p>
      <div class="flex gap-4">
        <div class="min-w-[260px]">
          <SmartDatePicker mode="range" :from-value="tbFrom" :to-value="tbTo" @change="onTrialBalanceRangeChange" />
        </div>
        <button class="bg-indigo-600 text-white px-3 py-1 rounded text-xs" @click="loadTrialBalance">عرض الميزان</button>
      </div>
    </div>

    <div v-if="trialBalance.length" class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-right">الكود</th>
            <th class="px-4 py-3 text-right">الاسم</th>
            <th class="px-4 py-3 text-right">النوع</th>
            <th class="px-4 py-3 text-right">مجموع المدين</th>
            <th class="px-4 py-3 text-right">مجموع الدائن</th>
            <th class="px-4 py-3 text-right">الرصيد</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="row in trialBalance" :key="row.code" class="hover:bg-gray-50">
            <td class="px-4 py-2 font-mono text-blue-600">{{ row.code }}</td>
            <td class="px-4 py-2">{{ row.name }}</td>
            <td class="px-4 py-2 text-gray-500 capitalize">{{ row.type }}</td>
            <td class="px-4 py-2 text-green-700">{{ fmt(row.total_debit) }}</td>
            <td class="px-4 py-2 text-red-600">{{ fmt(row.total_credit) }}</td>
            <td class="px-4 py-2 font-semibold" :class="row.balance >= 0 ? 'text-green-700' : 'text-red-600'">{{ fmt(Math.abs(row.balance)) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Accounts List -->
    <div class="bg-white rounded-xl shadow overflow-hidden mt-4">
      <div class="p-4 border-b flex justify-between items-center">
        <h2 class="font-semibold text-gray-700">قائمة الحسابات</h2>
        <input v-model="search" type="text" placeholder="بحث..." class="border rounded px-3 py-1 text-sm" />
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-right">الكود</th>
            <th class="px-4 py-3 text-right">الاسم</th>
            <th class="px-4 py-3 text-right">الاسم العربي</th>
            <th class="px-4 py-3 text-right">النوع</th>
            <th class="px-4 py-3 text-right">النوع الفرعي</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3 text-right">إجراءات</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-blue-600">{{ acc.code }}</td>
            <td class="px-4 py-3">{{ acc.name }}</td>
            <td class="px-4 py-3 text-gray-600">{{ acc.name_ar }}</td>
            <td class="px-4 py-3 capitalize text-gray-500">{{ acc.type }}</td>
            <td class="px-4 py-3 text-gray-400 text-xs">{{ acc.sub_type }}</td>
            <td class="px-4 py-3">
              <span :class="acc.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="px-2 py-0.5 rounded-full text-xs">
                {{ acc.is_active ? 'فعّال' : 'معطّل' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button
                  class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-100"
                  @click="goToLedger(acc)"
                >
                  كشف الحساب
                </button>
                <button
                  class="px-2 py-1 text-xs rounded"
                  :class="acc.is_active ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'"
                  @click="toggleAccountStatus(acc)"
                >
                  {{ acc.is_active ? 'تعطيل' : 'تفعيل' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import api from '@/services/api'
import { useRouter } from 'vue-router'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

const accounts     = ref<any[]>([])
const trialBalance = ref<any[]>([])
const search = ref('')
const tbFrom = ref('')
const tbTo   = ref('')
const router = useRouter()

function onTrialBalanceRangeChange(val: { from: string; to: string }) {
  tbFrom.value = val.from
  tbTo.value = val.to
}

async function loadAccounts() {
  const { data } = await api.get('/chart-of-accounts', { params: { search: search.value || undefined, per_page: 100 } })
  accounts.value = data.data.data ?? data.data
}

async function loadTrialBalance() {
  const { data } = await api.get('/ledger/trial-balance', { params: { from_date: tbFrom.value || undefined, to_date: tbTo.value || undefined } })
  trialBalance.value = data.data
}

onMounted(loadAccounts)
watch(search, loadAccounts)

function fmt(n: number) { return n ? Number(n).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) : '0.00' }

function goToLedger(acc: any) {
  router.push({ path: '/ledger', query: { account_id: acc.id, account_code: acc.code } })
}

async function toggleAccountStatus(acc: any) {
  if (acc?.is_system) return
  await api.put(`/chart-of-accounts/${acc.id}`, { is_active: !acc.is_active })
  await loadAccounts()
}
</script>
