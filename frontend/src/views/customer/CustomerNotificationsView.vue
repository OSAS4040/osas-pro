<template>
  <div class="space-y-4 py-1" dir="rtl">
    <div class="rounded-2xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 p-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-gray-900 dark:text-slate-100">الإشعارات والدعم الفني</h2>
          <p class="text-xs text-gray-500 dark:text-slate-400 mt-1">إدارة تنبيهاتك وطلبات الدعم الفني من نفس الواجهة.</p>
        </div>
        <div class="flex items-center gap-2">
          <button :class="tabClass('tickets')" class="px-3 py-2 rounded-lg text-xs font-semibold transition-all" @click="activeTab = 'tickets'">
            طلبات الدعم
          </button>
          <button :class="tabClass('kb')" class="px-3 py-2 rounded-lg text-xs font-semibold transition-all" @click="activeTab = 'kb'">
            قاعدة المعرفة
          </button>
          <button :class="tabClass('sla')" class="px-3 py-2 rounded-lg text-xs font-semibold transition-all" @click="activeTab = 'sla'">
            سياسات SLA
          </button>
          <button
            class="px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-xs font-semibold transition-colors"
            @click="openNewTicket = true"
          >
            + طلب دعم جديد
          </button>
        </div>
      </div>
    </div>

    <div v-if="stats && activeTab === 'tickets'" class="grid grid-cols-2 lg:grid-cols-5 gap-2">
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-3">
        <p class="text-[11px] text-gray-500">إجمالي الطلبات</p>
        <p class="text-base font-extrabold text-gray-900 dark:text-slate-100">{{ stats.total }}</p>
      </div>
      <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50/70 dark:bg-amber-900/20 p-3">
        <p class="text-[11px] text-amber-700 dark:text-amber-200">مفتوحة</p>
        <p class="text-base font-extrabold text-amber-800 dark:text-amber-100">{{ stats.open }}</p>
      </div>
      <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50/70 dark:bg-blue-900/20 p-3">
        <p class="text-[11px] text-blue-700 dark:text-blue-200">قيد المعالجة</p>
        <p class="text-base font-extrabold text-blue-800 dark:text-blue-100">{{ stats.in_progress }}</p>
      </div>
      <div class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-900/20 p-3">
        <p class="text-[11px] text-emerald-700 dark:text-emerald-200">محلولة</p>
        <p class="text-base font-extrabold text-emerald-800 dark:text-emerald-100">{{ stats.resolved }}</p>
      </div>
      <div class="rounded-xl border border-rose-200 dark:border-rose-800 bg-rose-50/70 dark:bg-rose-900/20 p-3">
        <p class="text-[11px] text-rose-700 dark:text-rose-200">متأخرة</p>
        <p class="text-base font-extrabold text-rose-800 dark:text-rose-100">{{ stats.overdue }}</p>
      </div>
    </div>

    <div v-if="activeTab === 'tickets'" class="space-y-3">
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-3">
        <div class="grid gap-2 md:grid-cols-4">
          <input
            v-model.trim="filters.search"
            class="field-sm md:col-span-2"
            placeholder="بحث بالموضوع أو رقم التذكرة"
            @input="fetchTickets(1)"
          >
          <select v-model="filters.status" class="field-sm" @change="fetchTickets(1)">
            <option value="">كل الحالات</option>
            <option value="open">مفتوحة</option>
            <option value="in_progress">قيد المعالجة</option>
            <option value="pending_customer">بانتظار العميل</option>
            <option value="resolved">محلولة</option>
            <option value="closed">مغلقة</option>
            <option value="escalated">مصعّدة</option>
          </select>
          <select v-model="filters.priority" class="field-sm" @change="fetchTickets(1)">
            <option value="">كل الأولويات</option>
            <option value="critical">حرجة</option>
            <option value="high">عالية</option>
            <option value="medium">متوسطة</option>
            <option value="low">منخفضة</option>
          </select>
        </div>
      </div>
      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 px-3 py-2 flex items-center justify-between text-xs text-gray-500 dark:text-slate-300">
        <span>عرض {{ pageStart }} - {{ pageEnd }} من {{ totalItems }}</span>
        <div class="flex items-center gap-2">
          <button type="button" class="px-2 py-1 rounded border border-gray-200 dark:border-slate-600 disabled:opacity-50" :disabled="currentPage <= 1" @click="fetchTickets(currentPage - 1)">السابق</button>
          <span>صفحة {{ currentPage }} / {{ totalPages }}</span>
          <button type="button" class="px-2 py-1 rounded border border-gray-200 dark:border-slate-600 disabled:opacity-50" :disabled="currentPage >= totalPages" @click="fetchTickets(currentPage + 1)">التالي</button>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 overflow-hidden">
        <div v-if="loading" class="p-8 text-center text-gray-500">جارٍ تحميل طلبات الدعم...</div>
        <div v-else-if="!tickets.length" class="p-8 text-center text-gray-500">لا توجد طلبات دعم حالياً.</div>
        <table v-else class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700">
            <tr>
              <th class="px-3 py-2 text-right">رقم الطلب</th>
              <th class="px-3 py-2 text-right">الموضوع</th>
              <th class="px-3 py-2 text-right">الأولوية</th>
              <th class="px-3 py-2 text-right">الحالة</th>
              <th class="px-3 py-2 text-right">تاريخ الإنشاء</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
            <tr
              v-for="ticket in tickets"
              :key="ticket.id"
              class="hover:bg-gray-50 dark:hover:bg-slate-800/60 cursor-pointer"
              @click="openTicket(ticket)"
            >
              <td class="px-3 py-2 font-mono text-xs text-primary-700 dark:text-primary-300">{{ ticket.ticket_number }}</td>
              <td class="px-3 py-2 text-gray-800 dark:text-slate-200">{{ ticket.subject }}</td>
              <td class="px-3 py-2">
                <PriorityBadge :priority="ticket.priority" />
              </td>
              <td class="px-3 py-2">
                <StatusBadge :status="ticket.status" />
              </td>
              <td class="px-3 py-2 text-xs text-gray-500">{{ formatDate(ticket.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="activeTab === 'kb'" class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-2">
      <KnowledgeBaseView />
    </div>

    <div v-if="activeTab === 'sla'" class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 p-2">
      <SlaManagementView />
    </div>

    <NewTicketModal v-if="openNewTicket" @close="openNewTicket = false" @created="onTicketCreated" />
    <TicketDetailDrawer
      v-if="selectedTicket"
      :ticket="selectedTicket"
      @close="selectedTicket = null"
      @updated="onTicketUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import axios from 'axios'
import PriorityBadge from '@/components/support/PriorityBadge.vue'
import StatusBadge from '@/components/support/StatusBadge.vue'
import NewTicketModal from '@/components/support/NewTicketModal.vue'
import TicketDetailDrawer from '@/components/support/TicketDetailDrawer.vue'
import KnowledgeBaseView from '@/components/support/KnowledgeBaseView.vue'
import SlaManagementView from '@/components/support/SlaManagementView.vue'
import { useToast } from '@/composables/useToast'

const activeTab = ref<'tickets' | 'kb' | 'sla'>('tickets')
const loading = ref(false)
const tickets = ref<any[]>([])
const stats = ref<any>(null)
const openNewTicket = ref(false)
const selectedTicket = ref<any>(null)
const toast = useToast()
const ticketStatusMap = ref<Record<string, string>>({})
let ticketPollTimer: ReturnType<typeof setInterval> | null = null
const currentPage = ref(1)
const totalPages = ref(1)
const totalItems = ref(0)
const pageStart = computed(() => (totalItems.value ? ((currentPage.value - 1) * 20) + 1 : 0))
const pageEnd = computed(() => Math.min(currentPage.value * 20, totalItems.value))

const filters = ref({
  search: '',
  status: '',
  priority: '',
})

const tabClass = (tab: 'tickets' | 'kb' | 'sla') => (
  tab === activeTab.value
    ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/35 dark:text-primary-200'
    : 'bg-white dark:bg-slate-900/50 text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-800'
)

async function fetchTickets(page = 1): Promise<void> {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/support/tickets', {
      params: { page, per_page: 20, ...filters.value },
    })
    const rows = data?.data?.data ?? []
    const meta = data?.data?.meta ?? data?.meta ?? null
    const current = Number(meta?.current_page ?? page)
    const last = Number(meta?.last_page ?? (rows.length ? current : 1))
    const total = Number(meta?.total ?? rows.length)
    currentPage.value = Number.isFinite(current) && current > 0 ? current : page
    totalPages.value = Number.isFinite(last) && last > 0 ? last : 1
    totalItems.value = Number.isFinite(total) && total >= 0 ? total : rows.length
    detectTicketStatusUpdates(rows)
    tickets.value = rows
  } finally {
    loading.value = false
  }
}

async function fetchStats(): Promise<void> {
  const { data } = await axios.get('/api/v1/support/stats')
  const payload = data?.data ?? null
  stats.value = payload
  const unread = calcUnreadCount(payload)
  localStorage.setItem('customer_unread_support_count', String(unread))
  window.dispatchEvent(new Event('support-unread-refresh'))
}

function openTicket(ticket: any): void {
  selectedTicket.value = ticket
}

function onTicketCreated(): void {
  openNewTicket.value = false
  void fetchTickets(1)
  void fetchStats()
}

function onTicketUpdated(): void {
  selectedTicket.value = null
  void fetchTickets(1)
  void fetchStats()
}

function calcUnreadCount(payload: any): number {
  const candidates = [
    payload?.unread,
    payload?.unread_count,
    payload?.pending_customer,
    (Number(payload?.open || 0) + Number(payload?.in_progress || 0)),
  ]
  for (const value of candidates) {
    const n = Number(value)
    if (Number.isFinite(n) && n >= 0) return n
  }
  return 0
}

function detectTicketStatusUpdates(rows: any[]): void {
  const next: Record<string, string> = {}
  let hasInitialSnapshot = Object.keys(ticketStatusMap.value).length > 0
  for (const row of rows) {
    const key = String(row?.id ?? '')
    const status = String(row?.status ?? '')
    if (!key || !status) continue
    const prev = ticketStatusMap.value[key]
    if (hasInitialSnapshot && prev && prev !== status) {
      toast.info('تحديث على طلب الدعم', `تغيرت حالة ${row.ticket_number || 'الطلب'} إلى: ${statusLabel(status)}`)
    }
    next[key] = status
  }
  if (!hasInitialSnapshot) hasInitialSnapshot = true
  ticketStatusMap.value = next
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    open: 'مفتوحة',
    in_progress: 'قيد المعالجة',
    pending_customer: 'بانتظار العميل',
    resolved: 'محلولة',
    closed: 'مغلقة',
    escalated: 'مصعّدة',
  }
  return map[status] || status
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => {
  void fetchTickets(1)
  void fetchStats()
  ticketPollTimer = setInterval(() => {
    void fetchTickets(1)
    void fetchStats()
  }, 30000)
})

onUnmounted(() => {
  if (ticketPollTimer) clearInterval(ticketPollTimer)
  ticketPollTimer = null
})
</script>
