<template>
  <div class="app-shell-page">
    <div class="page-head" :class="{ 'gap-2': staffUi.compactMode }">
      <div class="page-title-wrap">
        <h2 class="page-title-xl" :class="{ '!text-xl': staffUi.compactMode }">العمليات</h2>
        <p v-if="!staffUi.compactMode" class="page-subtitle">
          <template v-if="isExecutionPartner">
            متابعة العمليات قيد التنفيذ والمسلّمة والملغاة — بحث وتصفية حسب الحالة.
          </template>
          <template v-else>
            مراجعة أوامر العمل وتسجيلها في النظام — تتبّع الحالة والأولويات والفني عند التوفر.
          </template>
        </p>
      </div>
      <div class="text-xs text-gray-500 dark:text-slate-400">سجل العمليات</div>
    </div>

    <div class="table-toolbar" :class="{ '!py-2 !gap-1.5': staffUi.compactMode }">
      <input
        v-model="search"
        type="search"
        placeholder="بحث: رقم العملية، العميل، اللوحة..."
        class="table-search"
        :class="{ '!py-1.5 !text-sm': staffUi.compactMode }"
      />
      <button
        v-for="s in statusChips"
        :key="s.value"
        class="rounded-xl font-medium transition-colors"
        :class="[
          filterStatus === s.value ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-slate-800 text-gray-600 dark:text-slate-300 border border-gray-300 dark:border-slate-600',
          staffUi.compactMode ? 'px-2 py-1 text-xs' : 'px-3 py-1.5 text-xs',
        ]"
        @click="filterStatus = filterStatus === s.value ? '' : s.value; load()"
      >
        {{ s.label }}
      </button>
    </div>

    <div class="table-shell">
      <div class="panel-head" :class="{ '!py-2': staffUi.compactMode }">
        <span class="panel-title" :class="{ '!text-sm': staffUi.compactMode }">
          قائمة العمليات
        </span>
        <span v-if="!store.loading" class="panel-muted" :class="{ '!text-xs': staffUi.compactMode }">{{ filteredOrders.length }} عنصر</span>
      </div>
      <div v-if="store.loading" class="state-loading">جارٍ التحميل...</div>
      <table v-else class="data-table" :class="{ 'text-sm': staffUi.compactMode }">
        <thead>
          <tr>
            <th :class="{ '!py-2 !text-xs': staffUi.compactMode }">رقم العملية</th>
            <th :class="{ '!py-2 !text-xs': staffUi.compactMode }">العميل</th>
            <th :class="{ '!py-2 !text-xs': staffUi.compactMode }">المركبة</th>
            <th :class="{ '!py-2 !text-xs': staffUi.compactMode }">الفني</th>
            <th :class="{ '!py-2 !text-xs': staffUi.compactMode }">الحالة</th>
            <th v-if="!staffUi.compactMode">الأولوية</th>
            <th class="px-4 py-3" :class="{ '!py-2': staffUi.compactMode }"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="wo in filteredOrders" :key="wo.id">
            <td class="font-medium text-gray-900 dark:text-slate-100" :class="{ '!py-2': staffUi.compactMode }">{{ wo.order_number }}</td>
            <td :class="{ '!py-2': staffUi.compactMode }">{{ wo.customer?.name }}</td>
            <td class="font-mono text-xs" :class="{ '!py-2': staffUi.compactMode }">{{ wo.vehicle?.plate_number }}</td>
            <td :class="{ '!py-2': staffUi.compactMode }">{{ wo.assigned_technician?.name ?? '—' }}</td>
            <td :class="{ '!py-2': staffUi.compactMode }">
              <span :class="workOrderStatusBadgeClass(wo.status)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ workOrderStatusLabel(wo.status) }}</span>
            </td>
            <td v-if="!staffUi.compactMode">
              <span :class="priorityClass(wo.priority)" class="px-2 py-0.5 rounded-full text-xs">{{ priorityLabel(wo.priority) }}</span>
            </td>
            <td class="px-4 py-3 text-left" :class="{ '!py-2': staffUi.compactMode }">
              <RouterLink :to="`/work-orders/${wo.id}`" class="text-primary-600 hover:underline text-xs">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!filteredOrders.length">
            <td :colspan="staffUi.compactMode ? 6 : 7" class="table-empty">
              <p class="table-empty-title">{{ isExecutionPartner ? 'لا توجد عمليات ضمن التصفية الحالية' : 'لا يوجد في السجل عمليات منفّذة بعد' }}</p>
              <p class="table-empty-sub">{{ isExecutionPartner ? 'جرّب حالة أخرى أو امسح التصفية لعرض كل العمليات المعروضة' : 'غيّر المرشحات أو انتظر طلبات عمل جديدة للتنفيذ' }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useWorkOrderStore } from '@/stores/workOrder'
import { useStaffUiStore } from '@/stores/staffUi'
import { usePlatformExecutionPartner } from '@/composables/usePlatformExecutionPartner'
import { workOrderStatusLabel, workOrderStatusBadgeClass } from '@/utils/workOrderStatusLabels'

const store        = useWorkOrderStore()
const staffUi      = useStaffUiStore()
const route        = useRoute()
const { active: executionPartnerActive } = usePlatformExecutionPartner()
const isExecutionPartner = computed(() => executionPartnerActive.value)

/** شريك تنفيذ: فلاتر مبسّطة — قيم افتراضية للـ API (ليس مفاتيح WorkOrderStatus مباشرة) */
const filterStatus = ref<string>('')
const search = ref('')
const customerIdFilter = ref<number | null>(null)
const companyIdFilter = ref<number | null>(null)

const statusFilterValues = [
  'draft',
  'pending_manager_approval',
  'approved',
  'cancellation_requested',
  'in_progress',
  'on_hold',
  'completed',
  'delivered',
] as const

const executionPartnerStatusChips = [
  { value: 'in_progress', label: 'قيد التنفيذ' },
  { value: 'delivered', label: 'مسلّم' },
  { value: 'cancelled', label: 'ملغى' },
] as const

const statusChips = computed(() => {
  if (isExecutionPartner.value) {
    return [...executionPartnerStatusChips]
  }
  return statusFilterValues.map((value) => ({ value, label: workOrderStatusLabel(value) }))
})

function statusQueryForApi(): string | undefined {
  if (!isExecutionPartner.value) {
    return filterStatus.value || undefined
  }
  const v = filterStatus.value
  if (v === 'in_progress') return 'in_progress'
  if (v === 'delivered') return 'delivered'
  if (v === 'cancelled') return 'cancelled'
  /* بدون شريط محدد: ثلاث حالات المزوّد فقط */
  return 'in_progress,delivered,cancelled'
}

function syncCustomerFilterFromRoute(): void {
  const raw = route.query.customer_id
  if (raw !== undefined && raw !== null && String(raw).match(/^\d+$/)) {
    customerIdFilter.value = Number(raw)
  } else {
    customerIdFilter.value = null
  }
}

function syncCompanyFilterFromRoute(): void {
  const raw = route.query.company_id
  if (raw !== undefined && raw !== null && String(raw).match(/^\d+$/)) {
    companyIdFilter.value = Number(raw)
  } else {
    companyIdFilter.value = null
  }
}

async function load(): Promise<void> {
  await store.fetchOrders({
    status: statusQueryForApi(),
    ...(customerIdFilter.value ? { customer_id: customerIdFilter.value } : {}),
    ...(companyIdFilter.value ? { company_id: companyIdFilter.value } : {}),
  })
}

onMounted(() => {
  syncCustomerFilterFromRoute()
  syncCompanyFilterFromRoute()
  load()
})

watch(
  () => route.query.customer_id,
  () => {
    syncCustomerFilterFromRoute()
    load()
  },
)

watch(
  () => route.query.company_id,
  () => {
    syncCompanyFilterFromRoute()
    load()
  },
)

watch(executionPartnerActive, () => {
  filterStatus.value = ''
  load()
})

const filteredOrders = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return store.orders
  return store.orders.filter((wo: any) => {
    const hay = `${wo.order_number ?? ''} ${wo.customer?.name ?? ''} ${wo.vehicle?.plate_number ?? ''}`.toLowerCase()
    return hay.includes(q)
  })
})

function priorityClass(p: string): string {
  const m: Record<string, string> = {
    low: 'bg-gray-100 text-gray-500', normal: 'bg-blue-50 text-blue-600',
    high: 'bg-orange-100 text-orange-600', urgent: 'bg-red-100 text-red-700',
  }
  return m[p] ?? ''
}

function priorityLabel(p: string): string {
  const m: Record<string, string> = { low: 'منخفضة', normal: 'عادية', high: 'عالية', urgent: 'عاجلة' }
  return m[p] ?? p
}
</script>
