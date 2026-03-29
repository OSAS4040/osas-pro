<template>
  <div class="max-w-2xl mx-auto space-y-4">

    <!-- Back -->
    <div class="flex items-center gap-3">
      <RouterLink :to="`/vehicles/${vehicleId}`" class="text-gray-400 hover:text-gray-600 text-sm flex items-center gap-1">
        <ArrowRightIcon class="w-4 h-4" /> عودة لملف المركبة
      </RouterLink>
    </div>

    <div v-if="loading" class="text-center py-16 text-gray-400 text-sm">جارٍ التحميل...</div>

    <template v-else-if="vehicle">
      <!-- ═══ Digital Card ═══ -->
      <div ref="cardRef" class="relative rounded-2xl overflow-hidden shadow-2xl"
        :style="{ background: cardGradient }">

        <!-- Plate Badge top-right -->
        <div class="absolute top-4 left-4 bg-white/20 backdrop-blur-sm rounded-xl px-3 py-1.5 border border-white/30">
          <p class="text-xs text-white/70 text-center leading-none mb-0.5">لوحة</p>
          <p class="text-xl font-black text-white font-mono tracking-widest">{{ vehicle.plate_number }}</p>
        </div>

        <!-- Status Dot top-left -->
        <div class="absolute top-4 right-4 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full animate-pulse" :class="statusDot"></span>
          <span class="text-xs text-white/80 font-medium">{{ statusLabel }}</span>
        </div>

        <!-- Main Content -->
        <div class="pt-16 pb-6 px-6">
          <!-- Vehicle Icon + Name -->
          <div class="flex items-end gap-4 mb-5">
            <div class="w-20 h-20 rounded-2xl bg-white/15 backdrop-blur-sm border border-white/20 flex items-center justify-center flex-shrink-0">
              <TruckIcon class="w-10 h-10 text-white" />
            </div>
            <div>
              <p class="text-2xl font-black text-white">{{ vehicle.make }} {{ vehicle.model }}</p>
              <p class="text-white/70 text-sm">{{ vehicle.year }} · {{ vehicle.color }}</p>
              <p class="text-white/50 text-xs font-mono mt-0.5">VIN: {{ vehicle.vin || '—' }}</p>
            </div>
          </div>

          <!-- Stats Row -->
          <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 border border-white/10 text-center">
              <p class="text-xl font-black text-white">{{ vehicle.work_orders_count || 0 }}</p>
              <p class="text-xs text-white/60">زيارة</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 border border-white/10 text-center"
              :class="walletBalance < 0 ? 'ring-1 ring-red-400' : ''">
              <p class="text-xl font-black" :class="walletBalance < 0 ? 'text-red-300' : 'text-white'">{{ fmtMoney(walletBalance) }}</p>
              <p class="text-xs text-white/60">الرصيد</p>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 border border-white/10 text-center">
              <p class="text-xl font-black text-white">{{ loyaltyPoints }}</p>
              <p class="text-xs text-white/60">نقاط ولاء</p>
            </div>
          </div>

          <!-- Customer -->
          <div class="flex items-center justify-between bg-white/10 backdrop-blur-sm rounded-xl px-4 py-3 border border-white/10">
            <div class="flex items-center gap-3">
              <UserCircleIcon class="w-8 h-8 text-white/60" />
              <div>
                <p class="text-xs text-white/50">مالك المركبة</p>
                <p class="text-sm font-semibold text-white">{{ vehicle.customer?.name || '—' }}</p>
              </div>
            </div>
            <div class="text-left">
              <p class="text-xs text-white/50">آخر زيارة</p>
              <p class="text-xs text-white/80">{{ lastVisit }}</p>
            </div>
          </div>
        </div>

        <!-- Bottom QR strip -->
        <div class="bg-black/30 backdrop-blur-sm px-6 py-4 flex items-center justify-between border-t border-white/10">
          <div>
            <p class="text-xs text-white/40 mb-1">امسح للوصول السريع</p>
            <p class="text-[10px] text-white/30 font-mono truncate max-w-[160px]">{{ qrUrl }}</p>
          </div>
          <div class="bg-white p-2 rounded-xl">
            <img v-if="qrImageUrl" :src="qrImageUrl" class="w-16 h-16" alt="QR Code" />
            <div v-else class="w-16 h-16 bg-gray-100 rounded flex items-center justify-center text-[9px] text-gray-400">QR</div>
          </div>
        </div>
      </div>

      <!-- ═══ Active Work Orders ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <ClipboardDocumentIcon class="w-4 h-4 text-primary-600" /> أوامر العمل
          </h3>
          <RouterLink :to="`/work-orders?vehicle=${vehicleId}`" class="text-xs text-primary-600 hover:underline">عرض الكل</RouterLink>
        </div>
        <div v-if="workOrders.length" class="divide-y divide-gray-50 dark:divide-slate-700">
          <div v-for="wo in workOrders" :key="wo.id"
            class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                :class="woIconBg(wo.status)">
                <component :is="woIcon(wo.status)" class="w-4 h-4" :class="woIconColor(wo.status)" />
              </div>
              <div>
                <RouterLink :to="`/work-orders/${wo.id}`" class="text-sm font-medium text-gray-900 dark:text-slate-100 hover:text-primary-600">
                  {{ wo.order_number }}
                </RouterLink>
                <p class="text-xs text-gray-400">{{ wo.description?.substring(0, 40) || 'بدون وصف' }}</p>
              </div>
            </div>
            <div class="text-left">
              <span class="text-xs font-medium px-2 py-0.5 rounded-full" :class="woStatusClass(wo.status)">{{ woStatusLabel(wo.status) }}</span>
              <p class="text-xs text-gray-400 mt-0.5">{{ formatDate(wo.created_at) }}</p>
            </div>
          </div>
        </div>
        <p v-else class="text-center py-6 text-sm text-gray-400">لا توجد أوامر عمل</p>
      </div>

      <!-- ═══ Wallet Transactions ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <CreditCardIcon class="w-4 h-4 text-green-600" /> المحفظة والمعاملات
          </h3>
          <div class="flex items-center gap-2">
            <span class="text-sm font-bold" :class="walletBalance >= 0 ? 'text-green-600' : 'text-red-500'">
              {{ fmtMoney(walletBalance) }} ر.س
            </span>
          </div>
        </div>
        <div v-if="transactions.length" class="divide-y divide-gray-50 dark:divide-slate-700">
          <div v-for="tx in transactions.slice(0, 5)" :key="tx.id"
            class="flex items-center justify-between px-5 py-3">
            <div class="flex items-center gap-3">
              <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0"
                :class="tx.type === 'credit' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'">
                <ArrowUpIcon v-if="tx.type === 'credit'" class="w-3.5 h-3.5" />
                <ArrowDownIcon v-else class="w-3.5 h-3.5" />
              </div>
              <div>
                <p class="text-xs font-medium text-gray-800 dark:text-slate-200">{{ tx.description || 'معاملة' }}</p>
                <p class="text-[10px] text-gray-400">{{ formatDate(tx.created_at) }}</p>
              </div>
            </div>
            <span class="text-sm font-bold" :class="tx.type === 'credit' ? 'text-green-600' : 'text-red-500'">
              {{ tx.type === 'credit' ? '+' : '-' }}{{ fmtMoney(tx.amount) }}
            </span>
          </div>
        </div>
        <p v-else class="text-center py-6 text-sm text-gray-400">لا توجد معاملات</p>
      </div>

      <!-- ═══ Loyalty ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <StarIcon class="w-4 h-4 text-yellow-500" /> برنامج الولاء
          </h3>
        </div>
        <div class="p-5">
          <div class="flex items-center justify-between mb-3">
            <div>
              <p class="text-3xl font-black text-yellow-500">{{ loyaltyPoints }}</p>
              <p class="text-xs text-gray-400">نقطة متاحة</p>
            </div>
            <div class="text-left">
              <p class="text-xs text-gray-500">المستوى</p>
              <span class="text-sm font-bold px-3 py-1 rounded-full" :class="loyaltyTierClass">{{ loyaltyTier }}</span>
            </div>
          </div>
          <!-- Progress bar to next tier -->
          <div class="space-y-1.5">
            <div class="flex justify-between text-xs text-gray-400">
              <span>{{ loyaltyPoints }} / {{ loyaltyNextTier }} نقطة</span>
              <span>{{ loyaltyTierNext }}</span>
            </div>
            <div class="h-2 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                :style="{ width: `${Math.min((loyaltyPoints / loyaltyNextTier) * 100, 100)}%` }"
                :class="loyaltyBarClass"></div>
            </div>
          </div>
          <div class="mt-4 grid grid-cols-3 gap-2 text-center">
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ vehicle.work_orders_count || 0 }}</p>
              <p class="text-[10px] text-gray-400">زيارة كلية</p>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ fmtMoney(totalSpent) }}</p>
              <p class="text-[10px] text-gray-400">إجمالي الإنفاق</p>
            </div>
            <div class="bg-gray-50 dark:bg-slate-700 rounded-xl p-3">
              <p class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ pointsRedeemed }}</p>
              <p class="text-[10px] text-gray-400">نقاط محصلة</p>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ Tracking & Dashcam ═══ -->
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100 flex items-center gap-2">
            <MapPinIcon class="w-4 h-4 text-blue-600" /> التتبع والكاميرات
          </h3>
          <span v-if="vehicle.tracking_id" class="flex items-center gap-1.5 text-xs text-green-600 font-medium">
            <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> متصل
          </span>
          <span v-else class="text-xs text-gray-400">غير مرتبط</span>
        </div>
        <div class="p-5">
          <div v-if="vehicle.tracking_id" class="space-y-3">
            <!-- Live Map placeholder -->
            <div class="h-40 bg-gradient-to-br from-blue-50 to-teal-50 dark:from-blue-900/20 dark:to-teal-900/20 rounded-xl border border-blue-100 dark:border-blue-800 flex items-center justify-center relative overflow-hidden">
              <div class="absolute inset-0 opacity-20 dark:opacity-10" style="background-image: url('data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%236366f1\' fill-opacity=\'0.4\'%3E%3Cpath d=\'M20 20h1v1h-1z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
              <div class="text-center z-10">
                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-2 shadow-lg shadow-blue-500/40">
                  <MapPinIcon class="w-5 h-5 text-white" />
                </div>
                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">عرض الموقع الحي</p>
                <a :href="trackingUrl" target="_blank" class="text-xs text-blue-500 hover:underline mt-1 block">فتح في نظام التتبع ←</a>
              </div>
            </div>
            <!-- Dashcam -->
            <div v-if="vehicle.dashcam_id" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-xl">
              <VideoCameraIcon class="w-5 h-5 text-gray-500" />
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-800 dark:text-slate-200">داش كام متصل</p>
                <p class="text-xs text-gray-400">آخر مقطع: {{ lastDashcamEvent }}</p>
              </div>
              <a :href="dashcamUrl" target="_blank" class="text-xs bg-gray-200 dark:bg-slate-600 hover:bg-gray-300 px-2.5 py-1 rounded-lg transition-colors">مشاهدة</a>
            </div>
          </div>
          <div v-else class="text-center py-6 space-y-3">
            <MapPinIcon class="w-10 h-10 text-gray-200 mx-auto" />
            <p class="text-sm text-gray-400">لا يوجد نظام تتبع مرتبط</p>
            <RouterLink to="/settings/integrations" class="text-xs text-primary-600 hover:underline">ربط نظام تتبع من الإعدادات ←</RouterLink>
          </div>
        </div>
      </div>

      <!-- Share Actions -->
      <div class="flex gap-3">
        <button @click="downloadCard"
          class="flex-1 flex items-center justify-center gap-2 py-3 bg-primary-600 hover:bg-primary-700 text-white rounded-xl text-sm font-medium transition-colors">
          <ArrowDownTrayIcon class="w-4 h-4" /> تحميل البطاقة
        </button>

        <ShareModal
          :url="qrUrl"
          :title="`${vehicle?.make} ${vehicle?.model} — ${vehicle?.plate_number}`"
          label="البطاقة الرقمية"
          :phone="vehicle?.customer?.phone"
          :email="vehicle?.customer?.email"
          :message="`بطاقة مركبتك ${vehicle?.plate_number} — يمكنك متابعة أوامر العمل والرصيد:`"
          entity-type="vehicle_card"
          :entity-id="vehicleId"
          ref="shareModalRef"
        >
          <template #default="{ open }">
            <button @click="open"
              class="flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-medium transition-colors">
              <ShareIcon class="w-4 h-4" />
              مشاركة
            </button>
          </template>
        </ShareModal>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  TruckIcon, UserCircleIcon, ClipboardDocumentIcon, CreditCardIcon, StarIcon,
  MapPinIcon, VideoCameraIcon, ArrowUpIcon, ArrowDownIcon, ArrowRightIcon,
  ArrowDownTrayIcon, ShareIcon,
  ClockIcon, CheckCircleIcon, WrenchScrewdriverIcon, ExclamationCircleIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import ShareModal from '@/components/ShareModal.vue'
import { getQRImageUrl } from '@/utils/zatca'

const route = useRoute()
const vehicleId = Number(route.params.id)
const loading = ref(true)
const vehicle = ref<any>(null)
const workOrders = ref<any[]>([])
const transactions = ref<any[]>([])
const cardRef = ref<HTMLElement | null>(null)
const qrContainer = ref<HTMLElement | null>(null)

const qrUrl = computed(() => `${window.location.origin}/v/${vehicleId}`)
const qrImageUrl = computed(() => getQRImageUrl(qrUrl.value, 128))
const trackingUrl = computed(() => vehicle.value?.tracking_url || '#')
const dashcamUrl = computed(() => vehicle.value?.dashcam_url || '#')
const lastDashcamEvent = computed(() => vehicle.value?.last_dashcam_event || 'غير معروف')
const walletBalance = computed(() => vehicle.value?.wallet_balance ?? 0)
const loyaltyPoints = computed(() => vehicle.value?.loyalty_points ?? 0)
const totalSpent = computed(() => vehicle.value?.total_spent ?? 0)
const pointsRedeemed = computed(() => vehicle.value?.points_redeemed ?? 0)

const lastVisit = computed(() => {
  const wo = workOrders.value[0]
  if (!wo) return 'لا يوجد'
  return new Date(wo.created_at).toLocaleDateString('ar-SA-u-ca-gregory', { day: 'numeric', month: 'short', year: 'numeric' })
})

const cardGradient = computed(() => {
  const b = walletBalance.value
  const wo = workOrders.value.find(w => ['in_progress', 'assigned'].includes(w.status))
  if (wo) return 'linear-gradient(135deg, #1d4ed8 0%, #7c3aed 100%)'
  if (b < 0) return 'linear-gradient(135deg, #dc2626 0%, #9f1239 100%)'
  if (b === 0) return 'linear-gradient(135deg, #374151 0%, #111827 100%)'
  return 'linear-gradient(135deg, #059669 0%, #0369a1 100%)'
})

const statusDot = computed(() => {
  const wo = workOrders.value.find(w => ['in_progress', 'assigned'].includes(w.status))
  if (wo) return 'bg-blue-400'
  if (walletBalance.value < 0) return 'bg-red-400'
  return 'bg-green-400'
})

const statusLabel = computed(() => {
  const wo = workOrders.value.find(w => w.status === 'in_progress')
  if (wo) return 'في الورشة'
  const wo2 = workOrders.value.find(w => w.status === 'assigned')
  if (wo2) return 'موعد مجدول'
  if (walletBalance.value < 0) return 'رصيد سالب'
  return 'طبيعي'
})

const loyaltyTier = computed(() => {
  const p = loyaltyPoints.value
  if (p >= 5000) return 'بلاتيني'
  if (p >= 2000) return 'ذهبي'
  if (p >= 500) return 'فضي'
  return 'برونزي'
})
const loyaltyTierNext = computed(() => {
  const p = loyaltyPoints.value
  if (p >= 5000) return '✓ أعلى مستوى'
  if (p >= 2000) return 'بلاتيني'
  if (p >= 500) return 'ذهبي'
  return 'فضي'
})
const loyaltyNextTier = computed(() => {
  const p = loyaltyPoints.value
  if (p >= 5000) return 5000
  if (p >= 2000) return 5000
  if (p >= 500) return 2000
  return 500
})
const loyaltyTierClass = computed(() => {
  const t = loyaltyTier.value
  if (t === 'بلاتيني') return 'bg-purple-100 text-purple-700'
  if (t === 'ذهبي') return 'bg-yellow-100 text-yellow-700'
  if (t === 'فضي') return 'bg-gray-100 text-gray-600'
  return 'bg-orange-100 text-orange-700'
})
const loyaltyBarClass = computed(() => {
  const t = loyaltyTier.value
  if (t === 'بلاتيني') return 'bg-purple-500'
  if (t === 'ذهبي') return 'bg-yellow-400'
  return 'bg-gray-400'
})

function fmtMoney(v: number) {
  return new Intl.NumberFormat('ar-SA', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(v)
}
function formatDate(d: string) {
  return new Date(d).toLocaleDateString('ar-SA-u-ca-gregory', { day: 'numeric', month: 'short' })
}

function woStatus(s: string) { return s }
function woIcon(s: string) {
  if (s === 'completed') return CheckCircleIcon
  if (s === 'in_progress') return WrenchScrewdriverIcon
  if (s === 'pending' || s === 'new') return ClockIcon
  return ExclamationCircleIcon
}
function woIconBg(s: string) {
  if (s === 'completed') return 'bg-green-100'
  if (s === 'in_progress') return 'bg-blue-100'
  return 'bg-gray-100'
}
function woIconColor(s: string) {
  if (s === 'completed') return 'text-green-600'
  if (s === 'in_progress') return 'text-blue-600'
  return 'text-gray-500'
}
function woStatusClass(s: string) {
  if (s === 'completed') return 'bg-green-100 text-green-700'
  if (s === 'in_progress') return 'bg-blue-100 text-blue-700'
  if (s === 'invoiced') return 'bg-purple-100 text-purple-700'
  if (s === 'cancelled') return 'bg-red-100 text-red-500'
  return 'bg-gray-100 text-gray-600'
}
function woStatusLabel(s: string) {
  const m: Record<string, string> = {
    new: 'جديد', pending: 'في الانتظار', assigned: 'مكلّف',
    in_progress: 'جارٍ', completed: 'مكتمل', invoiced: 'مُفوتر', cancelled: 'ملغي',
  }
  return m[s] ?? s
}

function generateQR() {
  if (!qrContainer.value) return
  const url = qrUrl.value
  const size = 64
  qrContainer.value.innerHTML = `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg"><rect width="${size}" height="${size}" fill="white"/><text x="50%" y="50%" font-size="8" fill="#6366f1" text-anchor="middle" dominant-baseline="middle" font-family="monospace">QR</text><text x="50%" y="65%" font-size="5" fill="#94a3b8" text-anchor="middle" dominant-baseline="middle" font-family="monospace">#{vehicleId}</text></svg>`
}

async function downloadCard() {
  if (!cardRef.value) return
  try {
    const { default: html2canvas } = await import('html2canvas')
    const canvas = await html2canvas(cardRef.value, { scale: 3, useCORS: true })
    const a = document.createElement('a')
    a.download = `vehicle-card-${vehicle.value?.plate_number}.png`
    a.href = canvas.toDataURL()
    a.click()
  } catch {
    navigator.clipboard.writeText(qrUrl.value)
    alert('تم نسخ الرابط — html2canvas غير متاح')
  }
}

function shareCard() {
  if (navigator.share) {
    navigator.share({ title: `مركبة ${vehicle.value?.plate_number}`, url: qrUrl.value })
  } else {
    navigator.clipboard.writeText(qrUrl.value)
  }
}

onMounted(async () => {
  try {
    const res = await apiClient.get(`/vehicles/${vehicleId}/digital-card`)
    vehicle.value = res.data.data
    workOrders.value = res.data.work_orders || []
    transactions.value = res.data.transactions || []
  } catch { /* */ }
  finally {
    loading.value = false
    setTimeout(generateQR, 100)
  }
})
</script>
