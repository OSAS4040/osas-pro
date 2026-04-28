<template>
  <div data-testid="platform-support-root" class="mx-auto max-w-[1600px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
          الدعم الفني — عبر المشتركين
        </h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
          عرض ومتابعة تذاكر الدعم لكل الشركات من حساب مشغّل المنصة.
        </p>
      </div>
      <RouterLink
        :to="{ name: 'platform-overview' }"
        class="text-sm font-semibold text-primary-700 underline decoration-primary-300 underline-offset-2 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
      >
        ← الملخص التنفيذي
      </RouterLink>
    </div>

    <div v-if="stats" class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
      <StatCard label="الإجمالي" :value="stats.total" color="blue" icon="InboxIcon" />
      <StatCard label="مفتوحة" :value="stats.open" color="yellow" icon="ExclamationCircleIcon" />
      <StatCard label="قيد المعالجة" :value="stats.in_progress" color="indigo" icon="ArrowPathIcon" />
      <StatCard label="محلولة" :value="stats.resolved" color="green" icon="CheckCircleIcon" />
      <StatCard label="متأخرة ⚠️" :value="stats.overdue" color="red" icon="FireIcon" />
      <StatCard label="متوسط الحل" :value="`${stats.avg_resolution_hours}س`" color="purple" icon="ClockIcon" />
      <StatCard label="رضا العملاء" :value="`${stats.avg_satisfaction}/5`" color="amber" icon="StarIcon" />
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/40">
      <div class="flex flex-wrap gap-3">
        <input
          v-model.number="filters.company_id"
          type="number"
          min="1"
          placeholder="رقم الشركة (اختياري)"
          class="min-w-[10rem] flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          @change="fetchTickets(1)"
        />
        <input
          v-model="filters.search"
          placeholder="بحث بالموضوع أو رقم التذكرة..."
          class="min-w-[12rem] flex-[2] rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          @input="fetchTickets(1)"
        />
        <select
          v-model="filters.status"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          @change="fetchTickets(1)"
        >
          <option value="">كل الحالات</option>
          <option value="open">مفتوحة</option>
          <option value="in_progress">قيد المعالجة</option>
          <option value="pending_customer">انتظار العميل</option>
          <option value="resolved">محلولة</option>
          <option value="closed">مغلقة</option>
          <option value="escalated">مصعّدة</option>
        </select>
        <select
          v-model="filters.priority"
          class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-white"
          @change="fetchTickets(1)"
        >
          <option value="">كل الأولويات</option>
          <option value="critical">حرجة</option>
          <option value="high">عالية</option>
          <option value="medium">متوسطة</option>
          <option value="low">منخفضة</option>
        </select>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
          <input v-model="filters.overdue" type="checkbox" class="rounded" @change="fetchTickets(1)" />
          المتأخرة فقط
        </label>
      </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900/40">
      <div v-if="loading" class="p-12 text-center text-slate-400">
        <ArrowPathIcon class="mx-auto mb-2 h-8 w-8 animate-spin" />
        <p>جارٍ التحميل...</p>
      </div>
      <div v-else-if="tickets.length === 0" class="p-12 text-center text-slate-400">
        <LifebuoyIcon class="mx-auto mb-3 h-12 w-12 opacity-30" />
        <p>لا توجد تذاكر مطابقة</p>
      </div>
      <table v-else class="w-full text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-600 dark:bg-slate-800/80">
          <tr>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">رقم التذكرة</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">الشركة</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">الموضوع</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">الأولوية</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">الحالة</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">SLA</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">المسؤول</th>
            <th class="px-4 py-3 text-right font-semibold text-slate-600 dark:text-slate-300">التاريخ</th>
            <th class="px-4 py-3" />
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <tr
            v-for="t in tickets"
            :key="t.id"
            class="cursor-pointer transition-colors hover:bg-primary-50/50 dark:hover:bg-slate-800/60"
            @click="openTicket(t)"
          >
            <td class="px-4 py-3 font-mono text-xs font-semibold text-primary-600 dark:text-primary-400">
              {{ t.ticket_number }}
            </td>
            <td class="px-4 py-3">
              <RouterLink
                v-if="t.company_id"
                :to="{ name: 'platform-company-detail', params: { id: String(t.company_id) } }"
                class="font-medium text-primary-700 hover:underline dark:text-primary-400"
                @click.stop
              >
                {{ t.company?.name ?? '—' }}
              </RouterLink>
              <span v-else class="text-slate-500">—</span>
            </td>
            <td class="px-4 py-3">
              <div class="line-clamp-1 font-medium text-slate-900 dark:text-white">{{ t.subject }}</div>
              <div class="mt-0.5 text-xs text-slate-500">{{ categoryLabel(t.category) }}</div>
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
            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
              {{ t.assigned_to?.name || '—' }}
            </td>
            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">
              {{ formatDate(t.created_at) }}
            </td>
            <td class="px-4 py-3">
              <ChevronLeftIcon class="h-4 w-4 text-slate-400" />
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="pagination.last_page > 1"
        class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-sm dark:border-slate-700"
      >
        <span class="text-slate-500">{{ pagination.from }}–{{ pagination.to }} من {{ pagination.total }}</span>
        <div class="flex gap-1">
          <button
            v-for="p in pagination.last_page"
            :key="p"
            type="button"
            :class="p === pagination.current_page ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800'"
            class="h-8 w-8 rounded-lg text-xs font-medium transition-colors"
            @click="fetchTickets(p)"
          >
            {{ p }}
          </button>
        </div>
      </div>
    </div>

    <TicketDetailDrawer
      v-if="selectedTicket"
      :ticket="selectedTicket"
      tickets-api-base="/api/v1/platform/support/tickets"
      @close="selectedTicket = null"
      @updated="onTicketUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import apiClient from '@/lib/apiClient'
import StatCard from '@/components/support/StatCard.vue'
import PriorityBadge from '@/components/support/PriorityBadge.vue'
import StatusBadge from '@/components/support/StatusBadge.vue'
import SlaIndicator from '@/components/support/SlaIndicator.vue'
import TicketDetailDrawer from '@/components/support/TicketDetailDrawer.vue'
import { ArrowPathIcon, ChevronLeftIcon, LifebuoyIcon } from '@heroicons/vue/24/outline'

const TICKETS_PATH = 'platform/support/tickets'
const STATS_PATH = 'platform/support/stats'

const tickets = ref<any[]>([])
const stats = ref<any>(null)
const loading = ref(false)
const selectedTicket = ref<any>(null)
const route = useRoute()
const pagination = ref({ last_page: 1, current_page: 1, from: 1, to: 1, total: 0 })

const filters = ref({
  company_id: null as number | null,
  search: '',
  status: '',
  priority: '',
  overdue: false,
})

async function fetchTickets(page = 1) {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page, per_page: 15 }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.priority) params.priority = filters.value.priority
    if (filters.value.overdue) params.overdue = 'true'
    if (filters.value.company_id && Number(filters.value.company_id) > 0) {
      params.company_id = Number(filters.value.company_id)
    }
    const res = await apiClient.get(TICKETS_PATH, { params, skipGlobalErrorToast: true })
    const d = res.data.data
    tickets.value = d.data || []
    pagination.value = {
      last_page: d.last_page,
      current_page: d.current_page,
      from: d.from,
      to: d.to,
      total: d.total,
    }
  } catch {
    tickets.value = []
  } finally {
    loading.value = false
  }
}

async function fetchStats() {
  try {
    const res = await apiClient.get(STATS_PATH, { skipGlobalErrorToast: true })
    stats.value = res.data.data
  } catch {
    stats.value = null
  }
}

function openTicket(t: any) {
  selectedTicket.value = t
}

async function openTicketFromQueryIfPresent(): Promise<void> {
  const q = String(route.query.ticket ?? '').trim()
  if (q === '' || Number.isNaN(Number(q))) return
  const id = Number(q)
  const hit = tickets.value.find((t) => Number(t.id) === id)
  if (hit) {
    selectedTicket.value = hit
    return
  }
  try {
    const res = await apiClient.get(`${TICKETS_PATH}/${id}`, { skipGlobalErrorToast: true })
    if (res?.data?.data) selectedTicket.value = res.data.data
  } catch {
    // ignore non-authorized / not found deep-link attempts
  }
}

function onTicketUpdated() {
  void fetchTickets(pagination.value.current_page)
  void fetchStats()
  selectedTicket.value = null
}

const categoryMap: Record<string, string> = {
  financial: 'مالية',
  technical: 'تقنية',
  vehicle: 'مركبات',
  operational: 'تشغيلية',
  billing: 'فوترة',
  complaint: 'شكاوى',
  general: 'عامة',
}
function categoryLabel(c: string) {
  return categoryMap[c] || c
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('ar-SA', { day: 'numeric', month: 'short', year: 'numeric' })
}

onMounted(() => {
  void fetchTickets(1).then(() => openTicketFromQueryIfPresent())
  void fetchStats()
})
</script>
