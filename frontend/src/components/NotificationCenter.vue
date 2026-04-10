<template>
  <!-- شريط تنبيهات سريع (تحت الهيدر h-16) -->
  <Transition name="slide-down">
    <div v-if="notifications.length > 0 && !dismissed"
         data-print-chrome
         class="print:hidden fixed top-16 left-0 right-0 z-[45] bg-gradient-to-r from-blue-700 to-indigo-800 text-white text-xs px-4 py-2 flex items-center justify-between shadow-lg"
         dir="rtl"
    >
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <BellIcon class="w-4 h-4 flex-shrink-0 text-yellow-300 animate-pulse" />
        <div class="flex gap-4 overflow-x-auto no-scrollbar items-center">
          <span v-for="n in notifications" :key="n.id"
                class="flex items-center gap-1.5 cursor-pointer hover:text-yellow-200 transition-colors whitespace-nowrap"
                @click="handleStripClick(n)"
          >
            <span>{{ n.icon }}</span>
            <span>{{ n.message }}</span>
            <span v-if="n.badge" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 font-bold">{{ n.badge }}</span>
          </span>
        </div>
      </div>
      <button type="button" class="mr-3 p-1 hover:bg-white/20 rounded-lg transition-colors flex-shrink-0" @click="dismissed = true">
        <XMarkIcon class="w-4 h-4" />
      </button>
    </div>
  </Transition>

  <div ref="bellRef" data-print-chrome class="print:hidden relative">
    <button
      type="button"
      class="relative p-2 rounded-lg text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
      title="الإشعارات"
      aria-label="الإشعارات"
      @click="togglePanel"
    >
      <BellIcon class="w-5 h-5" />
      <span
        v-if="(props.apiSecurityNotice?.trim() ?? '') !== ''"
        class="absolute -top-0.5 -left-0.5 min-w-[14px] h-[14px] px-1 rounded-full bg-red-600 text-white text-[9px] leading-[14px] text-center font-bold ring-2 ring-white dark:ring-slate-800"
        title="تنبيه أمني API"
      >
        !
      </span>
      <span
        v-else-if="unreadCount > 0"
        class="absolute -top-0.5 -end-0.5 min-w-[18px] h-4 px-1 rounded-full bg-red-500 text-white text-[10px] leading-4 text-center font-bold ring-2 ring-white dark:ring-slate-800 tabular-nums"
      >
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <Transition name="fade-down">
      <div v-if="panelOpen"
           class="absolute end-0 top-full mt-2 w-80 max-w-[calc(100vw-2rem)] bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-gray-100 dark:border-slate-700 overflow-hidden z-50"
           dir="rtl"
      >
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-700">
          <h3 class="font-bold text-sm text-gray-900 dark:text-white">التنبيهات</h3>
          <button type="button" class="text-xs text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50" :disabled="markingAll || !allNotifications.length" @click="markAllRead">
            {{ markingAll ? '…' : 'تحديد كمقروء' }}
          </button>
        </div>
        <div class="max-h-80 overflow-y-auto">
          <div v-if="loading && !allNotifications.length" class="py-8 text-center text-gray-400 text-sm">
            جاري التحميل…
          </div>
          <div v-else-if="!allNotifications.length" class="py-8 text-center text-gray-400 text-sm">
            <BellSlashIcon class="w-8 h-8 mx-auto mb-2 text-gray-200 dark:text-slate-600" />
            لا توجد تنبيهات
          </div>
          <div v-for="n in allNotifications" :key="n.id"
               role="button"
               tabindex="0"
               class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer transition-colors border-b border-gray-50 dark:border-slate-700 last:border-0"
               :class="!n.read ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''"
               @click="handleNotifClick(n)"
               @keydown.enter="handleNotifClick(n)"
          >
            <span class="text-xl flex-shrink-0 mt-0.5">{{ n.icon }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-gray-800 dark:text-white">{{ n.title }}</p>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 line-clamp-2">{{ n.message }}</p>
              <p class="text-[10px] text-gray-300 dark:text-slate-600 mt-1">{{ n.time }}</p>
            </div>
            <span v-if="!n.read" class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 flex-shrink-0" />
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { BellIcon, BellSlashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const props = withDefaults(
  defineProps<{ apiSecurityNotice?: string }>(),
  { apiSecurityNotice: '' },
)

const router = useRouter()
const dismissed = ref(false)
const panelOpen = ref(false)
const bellRef = ref<HTMLElement | null>(null)
const loading = ref(false)
const markingAll = ref(false)

const OP_STORAGE_KEY = 'osaspro_dismissed_op_notifications'

function loadDismissedOps(): Set<string> {
  try {
    const raw = sessionStorage.getItem(OP_STORAGE_KEY)
    const arr = raw ? (JSON.parse(raw) as string[]) : []
    return new Set(Array.isArray(arr) ? arr : [])
  } catch {
    return new Set()
  }
}

function saveDismissedOps(set: Set<string>): void {
  try {
    sessionStorage.setItem(OP_STORAGE_KEY, JSON.stringify([...set]))
  } catch {
    /* تجاهل وضع المتصفح الخاص */
  }
}

let dismissedOps = loadDismissedOps()

interface Notif {
  id: string
  icon: string
  title: string
  message: string
  badge?: number
  read: boolean
  time: string
  route?: string
  source: 'db' | 'operational'
}

const allNotifications = ref<Notif[]>([])

const notifications = computed(() => allNotifications.value.filter(n => !n.read).slice(0, 4))
const unreadCount = computed(() => allNotifications.value.filter(n => !n.read).length)

const quiet = { skipGlobalErrorToast: true }

function togglePanel() { panelOpen.value = !panelOpen.value }

function formatTime(iso?: string): string {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
  } catch {
    return String(iso)
  }
}

function parseDbNotifications(res: { data?: Record<string, unknown> }): Notif[] {
  const body = res?.data as Record<string, unknown> | undefined
  const paginator = body?.data as Record<string, unknown> | unknown[] | undefined
  const rows = Array.isArray((paginator as Record<string, unknown>)?.data)
    ? (paginator as Record<string, unknown>).data as unknown[]
    : Array.isArray(paginator)
      ? paginator
      : []

  return rows.map((row: unknown): Notif => {
    const r = row as Record<string, unknown>
    const payload = r?.data && typeof r.data === 'object' && r.data !== null ? (r.data as Record<string, unknown>) : {}
    const title = (payload.title as string) || (payload.subject as string) || 'إشعار'
    const message = (payload.body as string) || (payload.message as string) || (payload.text as string) || ''
    const route = (payload.url as string) || (payload.link as string) || (payload.route as string)
    return {
      id: String(r.id),
      icon: (payload.icon as string) || '📌',
      title,
      message,
      read: r.read_at != null,
      time: formatTime(r.created_at as string | undefined),
      route: typeof route === 'string' ? route : undefined,
      source: 'db',
    }
  })
}

async function fetchOperationalAlerts(): Promise<Notif[]> {
  const out: Notif[] = []

  try {
    const inv = await apiClient.get('/reports/overdue-receivables', quiet)
    const overdue = (inv.data as { data?: { total_count?: number } })?.data?.total_count ?? 0
    if (overdue > 0 && !dismissedOps.has('op-overdue')) {
      out.push({
        id: 'op-overdue',
        icon: '⚠️',
        title: 'فواتير متأخرة',
        message: `${overdue} فاتورة بحاجة للتحصيل`,
        badge: overdue,
        read: false,
        time: 'الآن',
        route: '/invoices?status=overdue',
        source: 'operational',
      })
    }
  } catch { /* صلاحيات */ }

  try {
    const inv2 = await apiClient.get('/reports/inventory', { params: { low_stock: 1 }, ...quiet })
    const paginator = (inv2.data as { data?: { data?: unknown[] } })?.data
    const lowStock = Array.isArray(paginator?.data) ? paginator.data.length : 0
    if (lowStock > 0 && !dismissedOps.has('op-lowstock')) {
      out.push({
        id: 'op-lowstock',
        icon: '📦',
        title: 'مخزون منخفض',
        message: `${lowStock} منتج يحتاج تعبئة`,
        badge: lowStock,
        read: false,
        time: 'منذ قليل',
        route: '/inventory',
        source: 'operational',
      })
    }
  } catch { /* ... */ }

  try {
    const nps = await apiClient.get('/nps', { params: { unresolved: 1 }, ...quiet })
    const raw = (nps.data as { data?: { data?: unknown[] } })?.data?.data
      ?? (nps.data as { data?: unknown[] })?.data
      ?? []
    const low = Array.isArray(raw) ? raw.filter((r: unknown) => {
      const o = r as { score?: number; resolved?: boolean }
      return Number(o?.score) <= 2 && !o?.resolved
    }).length : 0
    if (low > 0 && !dismissedOps.has('op-nps')) {
      out.push({
        id: 'op-nps',
        icon: '🔴',
        title: 'تقييم منخفض',
        message: `${low} عميل غير راضٍ — يحتاج تدخلاً`,
        badge: low,
        read: false,
        time: 'منذ قليل',
        route: '/reports',
        source: 'operational',
      })
    }
  } catch { /* ... */ }

  try {
    const wr = await apiClient.get('/warranty-items', { params: { expiring_soon: 1 }, ...quiet })
    const exp = (wr.data as { data?: { data?: unknown[] } })?.data?.data?.length ?? 0
    if (exp > 0 && !dismissedOps.has('op-warranty')) {
      out.push({
        id: 'op-warranty',
        icon: '🛡️',
        title: 'ضمان قارب الانتهاء',
        message: `${exp} قطعة تنتهي ضمانها قريباً`,
        read: false,
        time: 'اليوم',
        route: undefined,
        source: 'operational',
      })
    }
  } catch { /* ... */ }

  return out
}

async function fetchAll(): Promise<void> {
  loading.value = true
  dismissedOps = loadDismissedOps()
  const merged: Notif[] = []

  try {
    const res = await apiClient.get('/notifications', { params: { per_page: 20 }, ...quiet })
    merged.push(...parseDbNotifications(res))
  } catch {
    /* غير مسموح أو غير متاح */
  }

  try {
    merged.push(...await fetchOperationalAlerts())
  } catch { /* ... */ }

  merged.sort((a, b) => {
    if (a.read !== b.read) return a.read ? 1 : -1
    return 0
  })

  allNotifications.value = merged
  loading.value = false
}

function handleStripClick(n: Notif) {
  void handleNotifClick(n)
  dismissed.value = true
}

async function handleNotifClick(n: Notif) {
  if (!n.read) {
    if (n.source === 'db') {
      try {
        await apiClient.put(`/notifications/${n.id}/read`, {}, quiet)
      } catch {
        /* صلاحيات users.update */
      }
    } else {
      dismissedOps.add(n.id)
      saveDismissedOps(dismissedOps)
    }
    n.read = true
  }
  if (n.route) {
    await router.push(n.route)
    panelOpen.value = false
  }
}

async function markAllRead() {
  markingAll.value = true
  try {
    await apiClient.put('/notifications/read-all', {}, quiet)
  } catch {
    /* قد تمنع الصلاحية */
  }

  for (const n of allNotifications.value) {
    if (n.source === 'operational') dismissedOps.add(n.id)
  }
  saveDismissedOps(dismissedOps)
  await fetchAll()
  markingAll.value = false
}

function onClickOutside(e: MouseEvent) {
  if (bellRef.value && !bellRef.value.contains(e.target as Node)) panelOpen.value = false
}

let pollId: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  void fetchAll()
  document.addEventListener('click', onClickOutside)
  pollId = setInterval(() => { void fetchAll() }, 120000)
})

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside)
  if (pollId) clearInterval(pollId)
})

watch(panelOpen, (open) => {
  if (open) void fetchAll()
})
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); opacity: 0; }
.fade-down-enter-active, .fade-down-leave-active { transition: all 0.2s ease; }
.fade-down-enter-from, .fade-down-leave-to { opacity: 0; transform: translateY(-8px); }
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>
