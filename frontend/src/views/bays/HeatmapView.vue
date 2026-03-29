<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950" dir="rtl">
    <div class="max-w-screen-2xl mx-auto px-4 py-6 space-y-6">

      <!-- ══ Page Header ══════════════════════════════════════════ -->
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
            الخريطة الحرارية — مناطق العمل
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            نظرة شاملة على حالة مناطق العمل والحجوزات
          </p>
        </div>
        <!-- Quick Actions -->
        <div class="flex items-center gap-2 flex-wrap">
          <button @click="goToToday"
            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <CalendarIcon class="w-4 h-4" />
            اليوم
          </button>
          <button @click="prevDay"
            class="p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <ChevronRightIcon class="w-4 h-4" />
          </button>
          <input v-model="date" type="date" @change="load"
            class="border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500" />
          <button @click="nextDay"
            class="p-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <ChevronLeftIcon class="w-4 h-4" />
          </button>
          <router-link to="/bookings"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition-colors shadow-sm">
            <PlusIcon class="w-4 h-4" />
            حجز جديد
          </router-link>
        </div>
      </div>

      <!-- ══ Analytics Summary Bar ═══════════════════════════════ -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col gap-1 shadow-sm">
          <span class="text-xs text-gray-500 dark:text-gray-400">إجمالي مناطق العمل</span>
          <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ analytics.total }}</span>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col gap-1 shadow-sm">
          <span class="text-xs text-gray-500 dark:text-gray-400">المشغولة الآن</span>
          <span class="text-2xl font-bold text-red-600 dark:text-red-400">{{ analytics.occupied }}</span>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col gap-1 shadow-sm">
          <span class="text-xs text-gray-500 dark:text-gray-400">المتاحة الآن</span>
          <span class="text-2xl font-bold text-green-600 dark:text-green-400">{{ analytics.available }}</span>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col gap-1 shadow-sm">
          <span class="text-xs text-gray-500 dark:text-gray-400">معدل الإشغال</span>
          <div class="flex items-end gap-1.5">
            <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ analytics.utilizationPct }}%</span>
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mt-1">
            <div class="h-1.5 rounded-full transition-all duration-500"
              :class="analytics.utilizationPct > 80 ? 'bg-red-500' : analytics.utilizationPct > 50 ? 'bg-amber-500' : 'bg-green-500'"
              :style="{ width: analytics.utilizationPct + '%' }"></div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col gap-1 shadow-sm">
          <span class="text-xs text-gray-500 dark:text-gray-400">حجوزات اليوم</span>
          <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ analytics.bookingsToday }}</span>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-20">
        <div class="flex flex-col items-center gap-3">
          <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600"></div>
          <span class="text-sm text-gray-500 dark:text-gray-400">جاري التحميل...</span>
        </div>
      </div>

      <template v-else>

        <!-- ══ Live Status Cards ════════════════════════════════════ -->
        <div>
          <h2 class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-3 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse inline-block"></span>
            الحالة المباشرة لمناطق العمل
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            <div v-for="bay in bays" :key="bay.id"
              class="relative bg-white dark:bg-gray-800 rounded-xl border-2 shadow-sm p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md cursor-default"
              :class="bayCardBorderClass(bay.status)">
              <!-- Bottleneck Badge -->
              <div v-if="bottleneckBays.has(bay.id)"
                class="absolute -top-2 -left-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full flex items-center gap-1 shadow">
                <ExclamationTriangleIcon class="w-3 h-3" />
                مرتفع
              </div>
              <div class="flex items-start justify-between mb-2">
                <div>
                  <p class="text-sm font-semibold text-gray-900 dark:text-white leading-tight">{{ bay.name }}</p>
                  <p class="text-xs text-gray-400 dark:text-gray-500">{{ bay.code }}</p>
                </div>
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-0.5" :class="bayStatusDot(bay.status)"></span>
              </div>
              <div class="mt-auto">
                <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full"
                  :class="bayStatusBadge(bay.status)">
                  {{ statusLabel(bay.status) }}
                </span>
                <p v-if="bay.status === 'occupied' && bay.current_work_order_id"
                  class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-1">
                  <TruckIcon class="w-3 h-3" />
                  أمر عمل #{{ bay.current_work_order_id }}
                </p>
                <p v-if="nextBookingFor(bay.id)" class="text-xs text-gray-400 dark:text-gray-500 mt-1 flex items-center gap-1">
                  <ClockIcon class="w-3 h-3" />
                  {{ nextBookingFor(bay.id) }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ Smart Heatmap Grid ═══════════════════════════════════ -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
              <FireIcon class="w-4 h-4 text-orange-500" />
              الخريطة الحرارية — {{ formatDateAr(date) }}
            </h2>
            <!-- Legend -->
            <div class="flex items-center gap-4 flex-wrap text-xs text-gray-500 dark:text-gray-400">
              <div class="flex items-center gap-1.5">
                <div class="w-4 h-4 rounded bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600"></div>
                فارغ
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-4 h-4 rounded" style="background: linear-gradient(135deg,#4ade80,#22c55e)"></div>
                منخفض
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-4 h-4 rounded" style="background: linear-gradient(135deg,#fbbf24,#f59e0b)"></div>
                متوسط
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-4 h-4 rounded" style="background: linear-gradient(135deg,#fb923c,#ef4444)"></div>
                مرتفع
              </div>
              <div class="flex items-center gap-1.5">
                <div class="w-4 h-4 rounded" style="background: linear-gradient(135deg,#ef4444,#991b1b)"></div>
                ممتلئ
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <div class="relative min-w-max">
              <!-- Current time indicator line -->
              <div v-if="isToday && currentHourOffset !== null"
                class="absolute top-0 bottom-0 w-0.5 bg-primary-500 z-20 pointer-events-none"
                :style="{ right: currentHourOffset + 'px' }">
                <div class="absolute -top-1 right-1/2 -translate-x-1/2 bg-primary-500 text-white text-[10px] px-1 py-0.5 rounded whitespace-nowrap">
                  الآن
                </div>
              </div>

              <!-- Hours Header -->
              <div class="flex border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <div class="w-40 flex-shrink-0 px-3 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 sticky right-0 bg-gray-50 dark:bg-gray-900 z-10">
                  منطقة العمل
                </div>
                <div class="w-20 flex-shrink-0 px-2 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 text-center">
                  الإشغال
                </div>
                <div v-for="h in hours" :key="h"
                  class="w-14 flex-shrink-0 px-1 py-3 text-xs text-center font-medium text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 last:border-r-0"
                  :class="h === currentHour && isToday ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 font-bold' : ''">
                  {{ h }}:00
                </div>
              </div>

              <!-- Bay Rows -->
              <div v-for="bay in heatmap" :key="bay.bay_id"
                class="flex border-b border-gray-200 dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-750 group transition-colors">
                <!-- Bay Name -->
                <div class="w-40 flex-shrink-0 px-3 py-3 border-r border-gray-200 dark:border-gray-700 sticky right-0 bg-white dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-750 z-10 transition-colors">
                  <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="bayStatusDot(getBayStatus(bay.bay_id))"></span>
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white leading-tight">{{ bay.bay_name }}</p>
                      <p class="text-xs text-gray-400 dark:text-gray-500">{{ bay.bay_code }}</p>
                    </div>
                  </div>
                </div>
                <!-- Utilization % -->
                <div class="w-20 flex-shrink-0 px-2 py-3 border-r border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center">
                  <span class="text-xs font-bold"
                    :class="bayUtilization(bay) > 80 ? 'text-red-600 dark:text-red-400' : bayUtilization(bay) > 50 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400'">
                    {{ bayUtilization(bay) }}%
                  </span>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1 mt-1">
                    <div class="h-1 rounded-full transition-all duration-500"
                      :class="bayUtilization(bay) > 80 ? 'bg-red-500' : bayUtilization(bay) > 50 ? 'bg-amber-500' : 'bg-green-500'"
                      :style="{ width: bayUtilization(bay) + '%' }"></div>
                  </div>
                  <ExclamationTriangleIcon v-if="bottleneckBays.has(bay.bay_id)" class="w-3 h-3 text-red-500 mt-1" />
                </div>
                <!-- Hour Cells -->
                <div v-for="h in hours" :key="h"
                  class="w-14 flex-shrink-0 p-1 border-r border-gray-200 dark:border-gray-700 last:border-r-0 relative"
                  :class="h === currentHour && isToday ? 'bg-primary-50/50 dark:bg-primary-900/10' : ''">
                  <div
                    class="h-10 rounded-md flex items-center justify-center cursor-pointer transition-all duration-200 hover:scale-105 hover:shadow-md hover:z-10 relative group/cell"
                    :style="cellStyle(bay.slots?.[h])"
                    @click="openCellDetail(bay, h)">
                    <span v-if="bay.slots?.[h]" class="text-xs font-bold text-white drop-shadow">{{ bay.slots[h] }}</span>
                    <!-- Tooltip -->
                    <div class="absolute bottom-full mb-2 right-1/2 translate-x-1/2 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg px-3 py-2 whitespace-nowrap opacity-0 group-hover/cell:opacity-100 pointer-events-none z-30 transition-opacity duration-150 shadow-xl">
                      <p class="font-semibold">{{ bay.bay_name }} — {{ h }}:00</p>
                      <p>{{ bay.slots?.[h] ? `${bay.slots[h]} حجز` : 'لا توجد حجوزات' }}</p>
                      <div class="absolute top-full right-1/2 translate-x-1/2 border-4 border-transparent border-t-gray-900 dark:border-t-gray-700"></div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!heatmap.length" class="text-center py-16 text-gray-400 dark:text-gray-500">
                <ChartBarIcon class="w-12 h-12 mx-auto mb-3 opacity-30" />
                <p>لا توجد بيانات لهذا اليوم</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ══ Bottleneck Alert Banner ══════════════════════════════ -->
        <div v-if="bottleneckBays.size > 0"
          class="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-xl p-4 flex items-start gap-3">
          <ExclamationTriangleIcon class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
          <div>
            <p class="text-sm font-semibold text-red-800 dark:text-red-300">تحذير: اختناق في مناطق العمل</p>
            <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">
              المناطق التالية تجاوزت نسبة إشغال 80%:
              <span class="font-bold">{{ bottleneckNames }}</span>
            </p>
          </div>
        </div>

        <!-- ══ Bookings Table ═══════════════════════════════════════ -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
          <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between flex-wrap gap-2">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
              <BookmarkIcon class="w-4 h-4 text-primary-500" />
              حجوزات {{ formatDateAr(date) }}
            </h3>
            <span class="inline-flex items-center gap-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 px-2.5 py-1 rounded-full font-medium">
              {{ bookings.length }} حجز
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 dark:bg-gray-900/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">منطقة العمل</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">البداية</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">المدة</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">الخدمة</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">الحالة</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr v-for="b in bookings" :key="b.id"
                  class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ b.bay?.name ?? b.bay_id }}</td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs font-mono">{{ formatTime(b.starts_at) }}</td>
                  <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">{{ b.duration_minutes }} دقيقة</td>
                  <td class="px-4 py-3 text-gray-700 dark:text-gray-200">{{ b.service_type }}</td>
                  <td class="px-4 py-3">
                    <span :class="bookingStatusBadge(b.status)" class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ bookingStatusLabel(b.status) }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!bookings.length">
                  <td colspan="5" class="text-center py-12 text-gray-400 dark:text-gray-500">
                    <BookmarkIcon class="w-8 h-8 mx-auto mb-2 opacity-30" />
                    لا توجد حجوزات لهذا اليوم
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </template>

      <!-- ══ Cell Detail Modal ════════════════════════════════════ -->
      <Transition name="fade">
        <div v-if="selectedCell" class="fixed inset-0 z-50 flex items-center justify-center p-4"
          @click.self="selectedCell = null">
          <div class="absolute inset-0 bg-black/40 dark:bg-black/60 backdrop-blur-sm"></div>
          <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 w-full max-w-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ selectedCell.bay.bay_name }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ selectedCell.hour }}:00 — {{ selectedCell.hour + 1 }}:00</p>
              </div>
              <button @click="selectedCell = null"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                <XMarkIcon class="w-5 h-5" />
              </button>
            </div>
            <div v-if="selectedCell.count > 0">
              <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                  :style="cellStyle(selectedCell.count)">
                  <span class="text-sm font-bold text-white">{{ selectedCell.count }}</span>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ selectedCell.count }} حجز في هذه الساعة</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedCell.bay.bay_name }} — {{ selectedCell.hour }}:00</p>
                </div>
              </div>
              <div class="mt-3 space-y-2">
                <div v-for="b in cellBookings" :key="b.id"
                  class="flex items-center justify-between p-2.5 bg-gray-50 dark:bg-gray-700/40 rounded-lg text-xs">
                  <span class="text-gray-700 dark:text-gray-300 font-medium">{{ b.service_type || 'خدمة' }}</span>
                  <span :class="bookingStatusBadge(b.status)" class="px-2 py-0.5 rounded-full font-medium">
                    {{ bookingStatusLabel(b.status) }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-6 text-gray-400 dark:text-gray-500">
              <CheckCircleIcon class="w-10 h-10 mx-auto mb-2 text-green-400" />
              <p class="text-sm font-medium text-gray-600 dark:text-gray-300">الفترة متاحة</p>
              <p class="text-xs mt-1">لا توجد حجوزات في هذه الساعة</p>
            </div>
            <button @click="selectedCell = null"
              class="mt-4 w-full py-2 text-sm font-medium rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
              إغلاق
            </button>
          </div>
        </div>
      </Transition>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import {
  CalendarIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  PlusIcon,
  FireIcon,
  ExclamationTriangleIcon,
  BookmarkIcon,
  ChartBarIcon,
  TruckIcon,
  ClockIcon,
  XMarkIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

// ── Types ────────────────────────────────────────────────────────────
interface HeatmapRow {
  bay_id: number
  bay_name: string
  bay_code: string
  status: string
  slots: Record<number, number>
}

interface Bay {
  id: number
  name: string
  code: string
  status: string
  current_work_order_id?: number | null
}

interface Booking {
  id: number
  bay_id: number
  bay?: { name: string }
  starts_at: string
  duration_minutes: number
  service_type: string
  status: string
}

interface SelectedCell {
  bay: HeatmapRow
  hour: number
  count: number
}

// ── State ────────────────────────────────────────────────────────────
const router  = useRouter()
const date    = ref(new Date().toISOString().slice(0, 10))
const heatmap = ref<HeatmapRow[]>([])
const bays    = ref<Bay[]>([])
const bookings = ref<Booking[]>([])
const loading = ref(true)
const selectedCell = ref<SelectedCell | null>(null)
const hours = Array.from({ length: 14 }, (_, i) => i + 7) // 7am – 8pm
const currentHour = ref(new Date().getHours())
const cellWidth = 56 // px, matches w-14

// Update current hour every minute
let clockTimer: ReturnType<typeof setInterval> | null = null

// ── Computed ─────────────────────────────────────────────────────────
const isToday = computed(() => date.value === new Date().toISOString().slice(0, 10))

const currentHourOffset = computed(() => {
  if (!isToday.value) return null
  const h = currentHour.value
  if (h < 7 || h > 20) return null
  // sticky col (w-40=160) + utilization col (w-20=80) + header offset
  const colsBefore = hours.indexOf(h)
  if (colsBefore < 0) return null
  return 160 + 80 + colsBefore * cellWidth + cellWidth / 2
})

const analytics = computed(() => {
  const total     = bays.value.length
  const occupied  = bays.value.filter(b => b.status === 'occupied').length
  const available = bays.value.filter(b => b.status === 'available').length
  const allSlots  = heatmap.value.reduce((sum, bay) => sum + Object.values(bay.slots || {}).reduce((a: number, v: any) => a + (v || 0), 0), 0)
  const maxSlots  = heatmap.value.length * hours.length
  const utilizationPct = maxSlots > 0 ? Math.round((allSlots / maxSlots) * 100) : 0
  return { total, occupied, available, utilizationPct, bookingsToday: bookings.value.length }
})

const bottleneckBays = computed(() => {
  const set = new Set<number>()
  for (const bay of heatmap.value) {
    if (bayUtilization(bay) > 80) set.add(bay.bay_id)
  }
  return set
})

const bottleneckNames = computed(() =>
  heatmap.value
    .filter(b => bottleneckBays.value.has(b.bay_id))
    .map(b => b.bay_name)
    .join('، ')
)

const cellBookings = computed(() => {
  if (!selectedCell.value) return []
  const { bay, hour } = selectedCell.value
  return bookings.value.filter(b => {
    if ((b.bay_id ?? (b.bay as any)?.id) !== bay.bay_id) return false
    const start = new Date(b.starts_at)
    return start.getHours() === hour
  })
})

// ── Helpers ──────────────────────────────────────────────────────────
function bayUtilization(bay: HeatmapRow): number {
  const filled = Object.values(bay.slots || {}).filter(v => v > 0).length
  return Math.round((filled / hours.length) * 100)
}

function getBayStatus(bayId: number): string {
  return bays.value.find(b => b.id === bayId)?.status ?? 'available'
}

function nextBookingFor(bayId: number): string | null {
  const now = new Date()
  const upcoming = bookings.value
    .filter(b => (b.bay_id ?? (b.bay as any)?.id) === bayId)
    .filter(b => new Date(b.starts_at) > now)
    .sort((a, b) => new Date(a.starts_at).getTime() - new Date(b.starts_at).getTime())
  if (!upcoming.length) return null
  return `التالي: ${formatTime(upcoming[0].starts_at)}`
}

function cellStyle(count: number | undefined) {
  if (!count) return { background: '' }
  if (count === 1) return { background: 'linear-gradient(135deg, #4ade80, #22c55e)', boxShadow: '0 2px 6px rgba(34,197,94,0.35)' }
  if (count === 2) return { background: 'linear-gradient(135deg, #fbbf24, #f59e0b)', boxShadow: '0 2px 6px rgba(245,158,11,0.35)' }
  if (count === 3) return { background: 'linear-gradient(135deg, #fb923c, #ef4444)', boxShadow: '0 2px 6px rgba(239,68,68,0.35)' }
  return { background: 'linear-gradient(135deg, #ef4444, #991b1b)', boxShadow: '0 2px 6px rgba(153,27,27,0.5)' }
}

function bayStatusDot(status: string) {
  return {
    'bg-green-500 animate-pulse': status === 'available',
    'bg-yellow-400':  status === 'reserved',
    'bg-red-500':     status === 'occupied',
    'bg-gray-400':    status === 'maintenance',
  }
}

function bayCardBorderClass(status: string) {
  return {
    'border-green-400 dark:border-green-600':  status === 'available',
    'border-yellow-400 dark:border-yellow-600': status === 'reserved',
    'border-red-400 dark:border-red-600':      status === 'occupied',
    'border-gray-300 dark:border-gray-600':    status === 'maintenance',
  }
}

function bayStatusBadge(status: string) {
  return {
    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400':   status === 'available',
    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': status === 'reserved',
    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400':            status === 'occupied',
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400':           status === 'maintenance',
  }
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    available: 'متاح',
    reserved: 'محجوز',
    occupied: 'مشغول',
    maintenance: 'صيانة',
  }
  return map[status] ?? status
}

function bookingStatusBadge(status: string) {
  return {
    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400': status === 'confirmed',
    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400':     status === 'pending',
    'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400':        status === 'cancelled',
  }
}

function bookingStatusLabel(status: string) {
  const map: Record<string, string> = { confirmed: 'مؤكد', pending: 'معلق', cancelled: 'ملغى' }
  return map[status] ?? status
}

function formatTime(d: string) {
  return new Date(d).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
}

function formatDateAr(d: string) {
  return new Date(d).toLocaleDateString('ar-SA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

// ── Actions ──────────────────────────────────────────────────────────
function goToToday() {
  date.value = new Date().toISOString().slice(0, 10)
  load()
}

function prevDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() - 1)
  date.value = d.toISOString().slice(0, 10)
  load()
}

function nextDay() {
  const d = new Date(date.value)
  d.setDate(d.getDate() + 1)
  date.value = d.toISOString().slice(0, 10)
  load()
}

function openCellDetail(bay: HeatmapRow, hour: number) {
  selectedCell.value = { bay, hour, count: bay.slots?.[hour] ?? 0 }
}

// ── Data Loading ─────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const [h, b, baysList] = await Promise.all([
      apiClient.get(`/bays/heatmap?date=${date.value}`),
      apiClient.get(`/bookings?date=${date.value}`),
      apiClient.get('/bays'),
    ])
    heatmap.value  = h.data?.data ?? []
    bookings.value = b.data?.data ?? []
    bays.value     = baysList.data?.data ?? []
  } finally {
    loading.value = false
  }
}

// ── Lifecycle ────────────────────────────────────────────────────────
onMounted(() => {
  load()
  clockTimer = setInterval(() => {
    currentHour.value = new Date().getHours()
  }, 60_000)
})

onUnmounted(() => {
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
