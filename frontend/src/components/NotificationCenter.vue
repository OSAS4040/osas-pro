<template>
  <!-- Notification Strip -->
  <Transition name="slide-down">
    <div v-if="notifications.length > 0 && !dismissed"
      class="fixed top-0 left-0 right-0 z-50 bg-gradient-to-r from-blue-700 to-indigo-800 text-white text-xs px-4 py-2 flex items-center justify-between shadow-lg"
      dir="rtl">
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <BellIcon class="w-4 h-4 flex-shrink-0 text-yellow-300 animate-pulse" />
        <div class="flex gap-4 overflow-x-auto no-scrollbar items-center">
          <span v-for="n in notifications" :key="n.id"
            @click="handleClick(n)"
            class="flex items-center gap-1.5 cursor-pointer hover:text-yellow-200 transition-colors whitespace-nowrap">
            <span>{{ n.icon }}</span>
            <span>{{ n.message }}</span>
            <span v-if="n.badge" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 font-bold">{{ n.badge }}</span>
          </span>
        </div>
      </div>
      <button @click="dismissed = true" class="mr-3 p-1 hover:bg-white/20 rounded-lg transition-colors flex-shrink-0">
        <XMarkIcon class="w-4 h-4" />
      </button>
    </div>
  </Transition>

  <!-- Notification Bell Dropdown -->
  <div class="relative" ref="bellRef">
    <button @click="togglePanel"
      class="relative p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors">
      <BellIcon class="w-5 h-5 text-gray-600 dark:text-slate-300" />
      <span v-if="unreadCount > 0"
        class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold leading-none">
        {{ unreadCount > 9 ? '9+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown Panel -->
    <Transition name="fade-down">
      <div v-if="panelOpen"
        class="absolute left-0 top-full mt-2 w-80 bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-gray-100 dark:border-slate-700 overflow-hidden z-50"
        dir="rtl">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-700">
          <h3 class="font-bold text-sm text-gray-900 dark:text-white">التنبيهات</h3>
          <button @click="markAllRead" class="text-xs text-blue-600 hover:underline">تحديد كمقروء</button>
        </div>
        <div class="max-h-80 overflow-y-auto">
          <div v-if="!allNotifications.length" class="py-8 text-center text-gray-400 text-sm">
            <BellSlashIcon class="w-8 h-8 mx-auto mb-2 text-gray-200" />
            لا توجد تنبيهات
          </div>
          <div v-for="n in allNotifications" :key="n.id"
            @click="handleNotifClick(n)"
            class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-slate-700 cursor-pointer transition-colors border-b border-gray-50 dark:border-slate-700 last:border-0"
            :class="!n.read ? 'bg-blue-50/50 dark:bg-blue-900/10' : ''">
            <span class="text-xl flex-shrink-0 mt-0.5">{{ n.icon }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-semibold text-gray-800 dark:text-white">{{ n.title }}</p>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5 truncate">{{ n.message }}</p>
              <p class="text-xs text-gray-300 dark:text-slate-600 mt-1">{{ n.time }}</p>
            </div>
            <span v-if="!n.read" class="w-2 h-2 bg-blue-500 rounded-full mt-1.5 flex-shrink-0"></span>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { BellIcon, BellSlashIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const router    = useRouter()
const dismissed = ref(false)
const panelOpen = ref(false)
const bellRef   = ref<HTMLElement | null>(null)

interface Notif {
  id: string
  icon: string
  title: string
  message: string
  badge?: number
  read: boolean
  time: string
  route?: string
}

const allNotifications = ref<Notif[]>([])
const notifications = computed(() => allNotifications.value.filter(n => !n.read).slice(0, 4))
const unreadCount   = computed(() => allNotifications.value.filter(n => !n.read).length)

function togglePanel() { panelOpen.value = !panelOpen.value }
function markAllRead() { allNotifications.value.forEach(n => n.read = true) }

function handleClick(n: any) {
  if (n.route) router.push(n.route)
  dismissed.value = true
}

function handleNotifClick(n: Notif) {
  n.read = true
  if (n.route) { router.push(n.route); panelOpen.value = false }
}

function onClickOutside(e: MouseEvent) {
  if (bellRef.value && !bellRef.value.contains(e.target as Node)) panelOpen.value = false
}

async function fetchAlerts() {
  const notifs: Notif[] = []
  try {
    const inv = await apiClient.get('/reports/overdue-receivables')
    const overdue = inv.data?.data?.total_count ?? 0
    if (overdue > 0) notifs.push({ id: 'overdue', icon: '⚠️', title: 'فواتير متأخرة', message: `${overdue} فاتورة بحاجة للتحصيل`, badge: overdue, read: false, time: 'الآن', route: '/invoices?status=overdue' })
  } catch {}
  try {
    const inv2 = await apiClient.get('/reports/inventory?low_stock=1')
    const lowStock = inv2.data?.data?.length ?? 0
    if (lowStock > 0) notifs.push({ id: 'lowstock', icon: '📦', title: 'مخزون منخفض', message: `${lowStock} منتج يحتاج تعبئة`, badge: lowStock, read: false, time: 'منذ قليل', route: '/inventory' })
  } catch {}
  try {
    const nps = await apiClient.get('/nps?unresolved=1')
    const low = (nps.data?.data?.data ?? []).filter((r: any) => r.score <= 2 && !r.resolved).length
    if (low > 0) notifs.push({ id: 'nps', icon: '🔴', title: 'تقييم منخفض', message: `${low} عميل غير راضٍ — يحتاج تدخلاً`, badge: low, read: false, time: 'منذ قليل', route: '/reports' })
  } catch {}
  // Warranty expiring
  try {
    const wr = await apiClient.get('/warranty-items?expiring_soon=1')
    const exp = wr.data?.data?.data?.length ?? 0
    if (exp > 0) notifs.push({ id: 'warranty', icon: '🛡️', title: 'ضمان قارب الانتهاء', message: `${exp} قطعة تنتهي ضمانها قريباً`, read: false, time: 'اليوم' })
  } catch {}
  allNotifications.value = notifs
}

onMounted(() => { fetchAlerts(); document.addEventListener('click', onClickOutside); setInterval(fetchAlerts, 60000) })
onUnmounted(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { transform: translateY(-100%); opacity: 0; }
.fade-down-enter-active, .fade-down-leave-active { transition: all 0.2s ease; }
.fade-down-enter-from, .fade-down-leave-to { opacity: 0; transform: translateY(-8px); }
.no-scrollbar::-webkit-scrollbar { display: none; }
</style>
