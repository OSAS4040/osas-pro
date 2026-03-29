<template>
  <div class="space-y-6" dir="rtl">
    <h2 class="text-2xl font-bold text-gray-900">الاشتراك والباقات</h2>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Current Plan Card -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center">
            <CreditCardIcon class="w-5 h-5 text-primary-600" />
          </div>
          <div>
            <h3 class="font-bold text-gray-900">الباقة الحالية</h3>
            <p class="text-sm text-gray-500">تفاصيل اشتراكك</p>
          </div>
        </div>
        <div class="bg-primary-50 rounded-xl p-4">
          <p class="text-2xl font-bold text-primary-700 capitalize">{{ sub?.plan ?? '—' }}</p>
          <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
            <div>
              <span class="text-gray-500">تاريخ البداية</span>
              <p class="font-medium text-gray-800">{{ formatDate(sub?.starts_at) }}</p>
            </div>
            <div>
              <span class="text-gray-500">تاريخ الانتهاء</span>
              <p class="font-medium text-gray-800">{{ formatDate(sub?.ends_at) }}</p>
            </div>
            <div>
              <span class="text-gray-500">الحالة</span>
              <span :class="sub?.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="inline-block px-2 py-0.5 rounded-full text-xs font-medium mt-0.5">
                {{ sub?.status === 'active' ? 'نشط' : sub?.status }}
              </span>
            </div>
            <div>
              <span class="text-gray-500">دورة الفوترة</span>
              <p class="font-medium text-gray-800">{{ sub?.billing_cycle === 'annual' ? 'سنوي' : 'شهري' }}</p>
            </div>
          </div>
        </div>
        <button @click="$router.push('/plans')"
          class="w-full py-2.5 border-2 border-primary-600 text-primary-700 rounded-xl font-semibold text-sm hover:bg-primary-50 transition-colors">
          تغيير الباقة
        </button>
      </div>

      <!-- Usage Limits Card -->
      <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
            <ChartBarIcon class="w-5 h-5 text-blue-600" />
          </div>
          <div>
            <h3 class="font-bold text-gray-900">حدود الاستخدام</h3>
            <p class="text-sm text-gray-500">استخدامك الحالي</p>
          </div>
        </div>
        <div class="space-y-3">
          <div v-for="item in usageItems" :key="item.label">
            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-600">{{ item.label }}</span>
              <span class="font-medium" :class="item.pct > 80 ? 'text-red-600' : 'text-gray-800'">
                {{ item.used }}/{{ item.max === -1 ? '∞' : item.max }}
              </span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all"
                :class="item.pct > 80 ? 'bg-red-500' : item.pct > 50 ? 'bg-yellow-500' : 'bg-green-500'"
                :style="{ width: item.max === -1 ? '5%' : `${Math.min(item.pct, 100)}%` }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Features List -->
    <div v-if="limits" class="bg-white rounded-2xl border border-gray-200 p-6">
      <h3 class="font-bold text-gray-900 mb-4">مميزات باقتك</h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div v-for="(val, key) in limits" :key="key"
          class="flex items-center gap-2 text-sm p-3 bg-gray-50 rounded-xl">
          <CheckCircleIcon class="w-4 h-4 text-green-500 flex-shrink-0" />
          <span class="text-gray-700">{{ featureLabel(String(key), val) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { CreditCardIcon, ChartBarIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'


const sub = ref<any>(null)
const usage = ref<any>(null)
const limits = ref<any>(null)
const loading = ref(true)

function formatDate(d: string) { return d ? new Date(d).toLocaleDateString('ar-SA') : '—' }

const usageItems = computed(() => {
  if (!usage.value || !limits.value) return []
  const u = usage.value
  const l = limits.value
  return [
    { label: 'الفروع',        used: u.branches    ?? 0, max: l.max_branches    ?? -1 },
    { label: 'المستخدمون',    used: u.users        ?? 0, max: l.max_users        ?? -1 },
    { label: 'المركبات',      used: u.vehicles     ?? 0, max: l.max_vehicles     ?? -1 },
    { label: 'المنتجات',      used: u.products     ?? 0, max: l.max_products     ?? -1 },
    { label: 'الفواتير/شهر', used: u.monthly_invoices ?? 0, max: l.max_monthly_invoices ?? -1 },
  ].map(i => ({ ...i, pct: i.max > 0 ? Math.round((i.used / i.max) * 100) : 0 }))
})

function featureLabel(key: string, val: any) {
  const map: Record<string, string> = {
    fleet_portal: 'بوابة الأسطول', bays_management: 'إدارة الرافعات',
    bookings: 'الحجوزات', heatmap: 'الخريطة الحرارية',
    analytics: 'التحليلات', multi_branch: 'متعدد الفروع',
    api_access: 'وصول API', zatca: 'ربط زاتكا',
  }
  const label = map[key] ?? key
  if (typeof val === 'boolean') return val ? `✓ ${label}` : `✗ ${label}`
  return `${label}: ${val}`
}

onMounted(async () => {
  loading.value = true
  try {
    const [s, u] = await Promise.all([apiClient.get('/subscription'), apiClient.get('/subscription/usage')])
    sub.value = s.data?.data?.subscription
    usage.value = u.data?.usage
    limits.value = u.data?.limits
  } finally { loading.value = false }
})
</script>
