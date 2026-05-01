<template>
  <div class="app-shell-page space-y-4" dir="rtl">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">ذكاء الأعمال</h2>
        <p class="page-subtitle">تحليل مخصص للعميل لدعم قرارات السداد والتشغيل</p>
      </div>
      <div class="page-toolbar">
        <RouterLink to="/customer/reports" class="btn btn-secondary">العودة للتقارير</RouterLink>
        <button type="button" class="btn btn-primary" :disabled="loading" @click="load">
          {{ loading ? 'جارٍ التحديث...' : 'تحديث' }}
        </button>
      </div>
    </div>

    <div class="card p-4 space-y-3">
      <p class="text-xs font-semibold text-gray-500">فترة التحليل</p>
      <div class="flex flex-wrap gap-2">
        <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-violet-50 text-violet-700 border border-violet-200" @click="applyPreset(30)">آخر 30 يوم</button>
        <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 border border-gray-200" @click="applyPreset(90)">آخر 90 يوم</button>
        <button type="button" class="px-3 py-1.5 text-xs rounded-lg bg-gray-50 border border-gray-200" @click="applyPreset(365)">آخر 12 شهر</button>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <input v-model="filters.from" type="date" class="field-sm">
        <input v-model="filters.to" type="date" class="field-sm">
      </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="card p-3">
        <p class="text-xs text-gray-500">نسبة السداد</p>
        <p class="text-xl font-bold text-emerald-700">{{ paymentRate.toFixed(1) }}%</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">متوسط الفاتورة</p>
        <p class="text-xl font-bold">{{ fmtMoney(avgInvoiceAmount) }}</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">زمن إغلاق الطلب</p>
        <p class="text-xl font-bold text-violet-700">{{ avgCompletionDays.toFixed(1) }} يوم</p>
      </div>
      <div class="card p-3">
        <p class="text-xs text-gray-500">تغطية الرصيد</p>
        <p class="text-xl font-bold" :class="walletCoverageRatio >= 1 ? 'text-emerald-700' : 'text-amber-700'">
          {{ walletCoverageRatio.toFixed(2) }}x
        </p>
      </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
      <div class="card p-4">
        <p class="text-sm font-semibold text-gray-900 mb-3">الاتجاه الشهري للفواتير</p>
        <div class="space-y-2">
          <div v-for="m in invoiceTrend" :key="m.key" class="grid grid-cols-[84px_1fr_90px] items-center gap-2 text-xs">
            <span class="text-gray-500">{{ m.label }}</span>
            <div class="h-2 rounded bg-gray-100 overflow-hidden">
              <div class="h-full rounded bg-violet-500" :style="{ width: `${m.percent}%` }" />
            </div>
            <span class="font-semibold text-gray-700 text-left">{{ fmtMoney(m.total) }}</span>
          </div>
          <p v-if="!invoiceTrend.length" class="text-xs text-gray-500">لا توجد بيانات ضمن الفترة.</p>
        </div>
      </div>

      <div class="card p-4">
        <p class="text-sm font-semibold text-gray-900 mb-3">توزيع أوامر العمل بالحالة</p>
        <div class="space-y-2">
          <div v-for="s in orderStatusRows" :key="s.key" class="flex items-center justify-between text-xs rounded-lg bg-gray-50 px-3 py-2">
            <span class="text-gray-700">{{ s.label }}</span>
            <span class="font-bold text-gray-900">{{ s.count.toLocaleString('ar-SA') }}</span>
          </div>
          <p v-if="!orderStatusRows.length" class="text-xs text-gray-500">لا توجد أوامر ضمن الفترة.</p>
        </div>
      </div>
    </div>

    <div class="card p-4">
      <p class="text-sm font-semibold text-gray-900 mb-3">توصيات ذكية للعميل</p>
      <ul class="space-y-2 text-xs text-gray-700">
        <li v-for="tip in recommendations" :key="tip" class="rounded-lg bg-gray-50 px-3 py-2">{{ tip }}</li>
      </ul>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'

const loading = ref(true)
const invoices = ref<any[]>([])
const workOrders = ref<any[]>([])
const wallets = ref<any[]>([])
const filters = reactive({ from: '', to: '' })

function fmtMoney(v: number): string {
  return v.toLocaleString('ar-SA', { style: 'currency', currency: 'SAR', maximumFractionDigits: 2 })
}
function invoiceAmount(inv: any): number {
  return Number(inv.total ?? inv.grand_total ?? 0)
}
function invoiceStatus(inv: any): string {
  return String(inv.status ?? '').toLowerCase()
}
function invoiceDate(inv: any): string {
  return String(inv.issue_date ?? inv.created_at ?? '')
}
function orderDate(wo: any): string {
  return String(wo.created_at ?? '')
}
function inDateRange(dateIso: string): boolean {
  if (!dateIso) return true
  const t = new Date(dateIso).getTime()
  if (Number.isNaN(t)) return true
  if (filters.from && t < new Date(filters.from).getTime()) return false
  if (filters.to && t > (new Date(filters.to).getTime() + 86399999)) return false
  return true
}
function extractList(payload: any): any[] {
  if (Array.isArray(payload?.data?.data)) return payload.data.data
  if (Array.isArray(payload?.data)) return payload.data
  if (Array.isArray(payload)) return payload
  return []
}
function monthKey(dateIso: string): string {
  const d = new Date(dateIso)
  if (Number.isNaN(d.getTime())) return 'unknown'
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

const filteredInvoices = computed(() => invoices.value.filter((x) => inDateRange(invoiceDate(x))))
const filteredOrders = computed(() => workOrders.value.filter((x) => inDateRange(orderDate(x))))
const totalPaid = computed(() => filteredInvoices.value.filter((x) => invoiceStatus(x).includes('paid') || invoiceStatus(x) === 'settled').reduce((s, x) => s + invoiceAmount(x), 0))
const totalDue = computed(() => filteredInvoices.value.filter((x) => !(invoiceStatus(x).includes('paid') || invoiceStatus(x) === 'settled')).reduce((s, x) => s + invoiceAmount(x), 0))
const paymentRate = computed(() => {
  const sum = totalPaid.value + totalDue.value
  return sum > 0 ? (totalPaid.value / sum) * 100 : 0
})
const avgInvoiceAmount = computed(() => {
  const c = filteredInvoices.value.length
  if (!c) return 0
  return filteredInvoices.value.reduce((s, x) => s + invoiceAmount(x), 0) / c
})
const avgCompletionDays = computed(() => {
  const completed = filteredOrders.value.filter((o) => ['completed', 'done', 'closed'].includes(String(o.status ?? '').toLowerCase()) && o.completed_at && o.created_at)
  if (!completed.length) return 0
  const totalDays = completed.reduce((s, o) => {
    const from = new Date(String(o.created_at)).getTime()
    const to = new Date(String(o.completed_at)).getTime()
    if (Number.isNaN(from) || Number.isNaN(to) || to <= from) return s
    return s + (to - from) / 86400000
  }, 0)
  return totalDays / completed.length
})
const walletBalance = computed(() => wallets.value.reduce((s, w) => s + Number(w?.balance ?? 0), 0))
const walletCoverageRatio = computed(() => (totalDue.value <= 0 ? 1 : walletBalance.value / totalDue.value))

const invoiceTrend = computed(() => {
  const buckets = new Map<string, number>()
  for (const inv of filteredInvoices.value) {
    const k = monthKey(invoiceDate(inv))
    if (k === 'unknown') continue
    buckets.set(k, (buckets.get(k) ?? 0) + invoiceAmount(inv))
  }
  const rows = [...buckets.entries()].sort(([a], [b]) => a.localeCompare(b)).slice(-6).map(([key, total]) => ({
    key,
    label: key,
    total,
  }))
  const max = Math.max(...rows.map((r) => r.total), 0)
  return rows.map((r) => ({ ...r, percent: max > 0 ? (r.total / max) * 100 : 0 }))
})

const orderStatusRows = computed(() => {
  const map = new Map<string, number>()
  for (const wo of filteredOrders.value) {
    const s = String(wo.status ?? 'unknown').toLowerCase()
    map.set(s, (map.get(s) ?? 0) + 1)
  }
  const toLabel = (s: string) => {
    if (['completed', 'done', 'closed'].includes(s)) return 'مكتمل'
    if (['cancelled', 'canceled'].includes(s)) return 'ملغي'
    if (['pending_manager_approval', 'awaiting_approval'].includes(s)) return 'بانتظار الاعتماد'
    if (['in_progress', 'processing', 'assigned'].includes(s)) return 'قيد التنفيذ'
    if (['draft', 'new', 'pending'].includes(s)) return 'جديد'
    return s || 'غير محدد'
  }
  return [...map.entries()].map(([key, count]) => ({ key, label: toLabel(key), count })).sort((a, b) => b.count - a.count)
})

const recommendations = computed(() => {
  const tips: string[] = []
  if (totalDue.value > walletBalance.value) tips.push('الرصيد الحالي أقل من إجمالي المستحقات؛ يفضل شحن المحفظة لتجنب تأخير الطلبات.')
  if (paymentRate.value < 70) tips.push('نسبة السداد منخفضة؛ راجع الفواتير غير المدفوعة وجدولة السداد.')
  if (avgCompletionDays.value > 5) tips.push('متوسط إغلاق أوامر العمل مرتفع؛ يفضّل مراجعة أولوية الطلبات والمتابعة على الطلبات المفتوحة.')
  if (!tips.length) tips.push('الأداء المالي والتشغيلي مستقر. استمر بمراجعة الاتجاهات شهريًا للحفاظ على الكفاءة.')
  return tips
})

function applyPreset(days: number): void {
  const to = new Date()
  const from = new Date()
  from.setDate(to.getDate() - days)
  filters.to = to.toISOString().split('T')[0]
  filters.from = from.toISOString().split('T')[0]
}

async function load(): Promise<void> {
  loading.value = true
  try {
    const [invRes, woRes, walletRes] = await Promise.allSettled([
      apiClient.get('/invoices', { params: { per_page: 300 } }),
      apiClient.get('/work-orders', { params: { per_page: 300 } }),
      apiClient.get('/wallet'),
    ])
    invoices.value = invRes.status === 'fulfilled' ? extractList(invRes.value.data) : []
    workOrders.value = woRes.status === 'fulfilled' ? extractList(woRes.value.data) : []
    if (walletRes.status === 'fulfilled') {
      const body = walletRes.value.data
      wallets.value = Array.isArray(body?.wallets?.data) ? body.wallets.data : Array.isArray(body?.wallets) ? body.wallets : extractList(body)
    } else {
      wallets.value = []
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  applyPreset(90)
  load()
})
</script>
