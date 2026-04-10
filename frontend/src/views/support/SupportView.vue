<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-900 p-6" dir="rtl">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <LifebuoyIcon class="w-7 h-7 text-blue-500" />
          مركز الدعم الفني
        </h1>
        <p class="text-sm text-gray-500 mt-1">إدارة التذاكر · قاعدة المعرفة · SLA</p>
      </div>
      <div class="flex gap-2">
        <button :class="tabClass('kb')" class="px-4 py-2 rounded-lg text-sm font-medium transition-all" @click="activeTab = 'kb'">
          <BookOpenIcon class="w-4 h-4 inline ml-1" /> قاعدة المعرفة
        </button>
        <button :class="tabClass('sla')" class="px-4 py-2 rounded-lg text-sm font-medium transition-all" @click="activeTab = 'sla'">
          <ClockIcon class="w-4 h-4 inline ml-1" /> سياسات SLA
        </button>
        <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium flex items-center gap-2 transition-all shadow-sm"
                @click="openNewTicket = true"
        >
          <PlusIcon class="w-4 h-4" /> تذكرة جديدة
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div v-if="stats" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
      <StatCard label="الإجمالي" :value="stats.total" color="blue" icon="InboxIcon" />
      <StatCard label="مفتوحة" :value="stats.open" color="yellow" icon="ExclamationCircleIcon" />
      <StatCard label="قيد المعالجة" :value="stats.in_progress" color="indigo" icon="ArrowPathIcon" />
      <StatCard label="محلولة" :value="stats.resolved" color="green" icon="CheckCircleIcon" />
      <StatCard label="متأخرة ⚠️" :value="stats.overdue" color="red" icon="FireIcon" />
      <StatCard label="متوسط الحل" :value="`${stats.avg_resolution_hours}س`" color="purple" icon="ClockIcon" />
      <StatCard label="رضا العملاء" :value="`${stats.avg_satisfaction}/5`" color="amber" icon="StarIcon" />
    </div>

    <!-- TICKETS TAB -->
    <div v-if="activeTab === 'tickets'">
      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 mb-4">
        <div class="flex flex-wrap gap-3">
          <input v-model="filters.search" placeholder="بحث بالموضوع أو رقم التذكرة..." class="flex-1 min-w-48 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none"
                 @input="fetchTickets()"
          />
          <select v-model="filters.status" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white"
                  @change="fetchTickets()"
          >
            <option value="">كل الحالات</option>
            <option value="open">مفتوحة</option>
            <option value="in_progress">قيد المعالجة</option>
            <option value="pending_customer">انتظار العميل</option>
            <option value="resolved">محلولة</option>
            <option value="closed">مغلقة</option>
            <option value="escalated">مصعّدة</option>
          </select>
          <select v-model="filters.priority" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white"
                  @change="fetchTickets()"
          >
            <option value="">كل الأولويات</option>
            <option value="critical">حرجة 🔴</option>
            <option value="high">عالية 🟠</option>
            <option value="medium">متوسطة 🟡</option>
            <option value="low">منخفضة 🟢</option>
          </select>
          <select v-model="filters.category" class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm dark:bg-gray-700 dark:text-white"
                  @change="fetchTickets()"
          >
            <option value="">كل الفئات</option>
            <option value="financial">مالية</option>
            <option value="technical">تقنية</option>
            <option value="vehicle">مركبات</option>
            <option value="operational">تشغيلية</option>
            <option value="billing">فوترة</option>
            <option value="complaint">شكاوى</option>
            <option value="general">عامة</option>
          </select>
          <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
            <input v-model="filters.overdue" type="checkbox" class="rounded" @change="fetchTickets()" />
            المتأخرة فقط
          </label>
        </div>
        <div class="flex flex-wrap gap-2 mt-3">
          <span class="text-[10px] text-gray-400 w-full">اختصارات ذكية:</span>
          <button v-for="q in quickFilters" :key="q.label" type="button" class="px-2.5 py-1 rounded-lg text-[11px] font-medium border border-gray-200 dark:border-gray-600 hover:bg-blue-50 dark:hover:bg-gray-700 transition-colors"
                  @click="applyQuick(q)"
          >
            {{ q.label }}
          </button>
        </div>
      </div>

      <!-- Tickets Table -->
      <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div v-if="loading" class="p-12 text-center text-gray-400">
          <ArrowPathIcon class="w-8 h-8 animate-spin mx-auto mb-2" />
          <p>جارٍ التحميل...</p>
        </div>
        <div v-else-if="tickets.length === 0" class="p-12 text-center text-gray-400">
          <LifebuoyIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p>لا توجد تذاكر</p>
        </div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
            <tr>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">رقم التذكرة</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الموضوع</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الأولوية</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الحالة</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">SLA</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">المسؤول</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">التاريخ</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr v-for="t in tickets" :key="t.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors cursor-pointer"
                @click="openTicket(t)"
            >
              <td class="px-4 py-3 font-mono text-xs text-blue-600 dark:text-blue-400 font-semibold">{{ t.ticket_number }}</td>
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900 dark:text-white line-clamp-1">{{ t.subject }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ categoryLabel(t.category) }}</div>
              </td>
              <td class="px-4 py-3">
                <PriorityBadge :priority="t.priority" />
              </td>
              <td class="px-4 py-3">
                <StatusBadge :status="t.status" />
              </td>
              <td class="px-4 py-3">
                <SlaIndicator :ticket="t" />
              </td>
              <td class="px-4 py-3 text-gray-600 dark:text-gray-400 text-xs">
                {{ t.assigned_to?.name || '—' }}
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                {{ formatDate(t.created_at) }}
              </td>
              <td class="px-4 py-3">
                <ChevronLeftIcon class="w-4 h-4 text-gray-400" />
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm">
          <span class="text-gray-500">{{ pagination.from }}–{{ pagination.to }} من {{ pagination.total }}</span>
          <div class="flex gap-1">
            <button v-for="p in pagination.last_page" :key="p" :class="p === pagination.current_page ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700'"
                    class="w-8 h-8 rounded-lg text-xs font-medium transition-all"
                    @click="fetchTickets(p)"
            >
              {{ p }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- KNOWLEDGE BASE TAB -->
    <div v-if="activeTab === 'kb'">
      <KnowledgeBaseView @back="activeTab = 'tickets'" />
    </div>

    <!-- SLA TAB -->
    <div v-if="activeTab === 'sla'">
      <SlaManagementView @back="activeTab = 'tickets'" />
    </div>

    <!-- New Ticket Modal -->
    <NewTicketModal v-if="openNewTicket" @close="openNewTicket = false" @created="onTicketCreated" />

    <!-- Ticket Detail Drawer -->
    <TicketDetailDrawer v-if="selectedTicket" :ticket="selectedTicket"
                        @close="selectedTicket = null" @updated="onTicketUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import {
  LifebuoyIcon, PlusIcon, BookOpenIcon, ClockIcon,
  ArrowPathIcon, ChevronLeftIcon,
} from '@heroicons/vue/24/outline'
import StatCard from '@/components/support/StatCard.vue'
import PriorityBadge from '@/components/support/PriorityBadge.vue'
import StatusBadge from '@/components/support/StatusBadge.vue'
import SlaIndicator from '@/components/support/SlaIndicator.vue'
import NewTicketModal from '@/components/support/NewTicketModal.vue'
import TicketDetailDrawer from '@/components/support/TicketDetailDrawer.vue'
import KnowledgeBaseView from '@/components/support/KnowledgeBaseView.vue'
import SlaManagementView from '@/components/support/SlaManagementView.vue'

const activeTab    = ref('tickets')
const tickets      = ref<any[]>([])
const stats        = ref<any>(null)
const loading      = ref(false)
const openNewTicket = ref(false)
const selectedTicket = ref<any>(null)
const pagination   = ref({ last_page: 1, current_page: 1, from: 1, to: 1, total: 0 })

const filters = ref({
  search: '', status: '', priority: '', category: '', overdue: false,
})

const quickFilters: { label: string; priority?: string; status?: string; category?: string; overdue: boolean }[] = [
  { label: '🔴 حرجة اليوم', priority: 'critical', overdue: false },
  { label: '⏱️ متأخرة', overdue: true },
  { label: '🛠️ تقنية', category: 'technical', overdue: false },
  { label: '🚗 مركبات', category: 'vehicle', overdue: false },
]

function applyQuick(q: (typeof quickFilters)[number]) {
  filters.value = {
    search: '',
    status: q.status ?? '',
    priority: q.priority ?? '',
    category: q.category ?? '',
    overdue: q.overdue,
  }
  fetchTickets(1)
}

const tabClass = (tab: string) => tab === activeTab.value
  ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'
  : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'

async function fetchTickets(page = 1) {
  loading.value = true
  try {
    const params: any = { page, per_page: 15, ...filters.value }
    if (params.overdue) params.overdue = 'true'
    else delete params.overdue
    const res = await axios.get('/api/v1/support/tickets', { params })
    const d   = res.data.data
    tickets.value    = d.data || []
    pagination.value = { last_page: d.last_page, current_page: d.current_page, from: d.from, to: d.to, total: d.total }
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  const res = await axios.get('/api/v1/support/stats')
  stats.value = res.data.data
}

function openTicket(t: any) { selectedTicket.value = t }

function onTicketCreated(_t: any) {
  openNewTicket.value = false
  fetchTickets()
  fetchStats()
}

function onTicketUpdated() {
  fetchTickets()
  fetchStats()
  selectedTicket.value = null
}

const categoryMap: Record<string, string> = {
  financial: 'مالية', technical: 'تقنية', vehicle: 'مركبات',
  operational: 'تشغيلية', billing: 'فوترة', complaint: 'شكاوى', general: 'عامة',
}
const categoryLabel = (c: string) => categoryMap[c] || c

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => { fetchTickets(); fetchStats() })
</script>
