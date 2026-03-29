<template>
  <div dir="rtl" class="min-h-screen bg-gray-50 dark:bg-slate-950 p-4 sm:p-6">

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center items-center h-60">
      <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="invoice" class="max-w-3xl mx-auto space-y-4">

      <!-- Header Actions -->
      <div class="flex items-center justify-between">
        <button @click="$router.back()" class="flex items-center gap-1.5 text-gray-500 hover:text-gray-700 dark:text-slate-400 text-sm">
          <ChevronRightIcon class="w-4 h-4" />
          رجوع
        </button>
        <div class="flex gap-2">
          <button @click="printInvoice" class="btn btn-outline text-xs py-1.5 px-3">
            <PrinterIcon class="w-3.5 h-3.5 mr-1" /> طباعة
          </button>
          <button @click="shareWhatsApp" class="btn bg-green-500 hover:bg-green-600 text-white text-xs py-1.5 px-3 flex items-center gap-1">
            <span class="text-sm">📱</span> واتساب
          </button>
          <button @click="downloadPDF" class="btn btn-primary text-xs py-1.5 px-3">
            <ArrowDownTrayIcon class="w-3.5 h-3.5 mr-1" /> PDF
          </button>
        </div>
      </div>

      <!-- Main Invoice Card -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden" id="invoice-print-area">

        <!-- Company Header -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-900 px-6 py-5 text-white">
          <div class="flex justify-between items-start">
            <div>
              <h1 class="text-xl font-bold">{{ invoice.company?.name ?? 'فاتورة ضريبية' }}</h1>
              <p class="text-blue-200 text-sm mt-0.5">{{ invoice.company?.vat_number ? 'رقم ضريبي: ' + invoice.company.vat_number : '' }}</p>
            </div>
            <div class="text-left">
              <p class="text-2xl font-mono font-bold">#{{ invoice.invoice_number }}</p>
              <p class="text-blue-200 text-xs mt-0.5">{{ formatDate(invoice.issued_at) }}</p>
            </div>
          </div>
        </div>

        <!-- Status Bar -->
        <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50 dark:bg-slate-900 border-b border-gray-100 dark:border-slate-700">
          <span :class="statusClass(invoice.status)" class="px-3 py-1 rounded-full text-xs font-bold">
            {{ statusLabel(invoice.status) }}
          </span>
          <span class="text-sm text-gray-500 dark:text-slate-400">{{ formatDate(invoice.issued_at) }}</span>
        </div>

        <!-- Customer + Vehicle Info -->
        <div class="grid grid-cols-2 gap-4 px-6 py-4 border-b border-gray-100 dark:border-slate-700">
          <div>
            <p class="text-xs text-gray-400 mb-1">العميل</p>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ invoice.customer?.name ?? '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">{{ invoice.customer?.phone ?? '' }}</p>
          </div>
          <div v-if="invoice.vehicle">
            <p class="text-xs text-gray-400 mb-1">المركبة</p>
            <p class="font-semibold text-gray-900 dark:text-white text-sm font-mono">{{ invoice.vehicle.plate_number }}</p>
            <p class="text-xs text-gray-500 dark:text-slate-400">{{ invoice.vehicle.make }} {{ invoice.vehicle.model }}</p>
          </div>
        </div>

        <!-- Items Table -->
        <div class="px-6 py-4">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-gray-400 dark:text-slate-500 text-xs border-b border-gray-100 dark:border-slate-700">
                <th class="text-right pb-2 font-medium">البند</th>
                <th class="text-center pb-2 font-medium w-16">الكمية</th>
                <th class="text-center pb-2 font-medium w-24">السعر</th>
                <th class="text-center pb-2 font-medium w-20">الضريبة</th>
                <th class="text-left pb-2 font-medium w-24">الإجمالي</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700">
              <tr v-for="item in invoice.items" :key="item.id" class="py-2">
                <td class="py-2.5 text-gray-800 dark:text-slate-200">{{ item.name }}</td>
                <td class="text-center text-gray-600 dark:text-slate-400">{{ item.quantity }}</td>
                <td class="text-center text-gray-600 dark:text-slate-400">{{ fmt(item.unit_price) }}</td>
                <td class="text-center text-gray-500 dark:text-slate-500 text-xs">%{{ item.vat_rate ?? 15 }}</td>
                <td class="text-left font-medium text-gray-800 dark:text-white">{{ fmt(item.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="px-6 pb-4 border-t border-gray-100 dark:border-slate-700">
          <div class="max-w-xs mr-auto space-y-1.5 mt-3">
            <div class="flex justify-between text-sm text-gray-600 dark:text-slate-400">
              <span>المجموع قبل الضريبة</span>
              <span class="font-mono">{{ fmt(invoice.subtotal) }}</span>
            </div>
            <div v-if="invoice.discount_amount > 0" class="flex justify-between text-sm text-green-600">
              <span>الخصم</span>
              <span class="font-mono">- {{ fmt(invoice.discount_amount) }}</span>
            </div>
            <div class="flex justify-between text-sm text-gray-600 dark:text-slate-400">
              <span>ضريبة القيمة المضافة (15%)</span>
              <span class="font-mono">{{ fmt(invoice.tax_amount) }}</span>
            </div>
            <div class="flex justify-between font-bold text-lg text-gray-900 dark:text-white border-t border-gray-200 dark:border-slate-600 pt-2 mt-2">
              <span>الإجمالي النهائي</span>
              <span class="font-mono text-blue-700 dark:text-blue-400">{{ fmt(invoice.total) }} ر.س</span>
            </div>
          </div>
        </div>

        <!-- Visual Price Breakdown (Pie Chart) -->
        <div class="px-6 py-4 bg-blue-50 dark:bg-slate-900 border-t border-blue-100 dark:border-slate-700">
          <p class="text-xs font-bold text-gray-600 dark:text-slate-300 mb-3">أين ذهبت أموالك؟</p>
          <div class="flex items-center gap-4">
            <svg viewBox="0 0 36 36" class="w-16 h-16 flex-shrink-0">
              <path :d="pieSlice(0, partsPercent)" fill="#3b82f6" />
              <path :d="pieSlice(partsPercent, laborPercent)" fill="#10b981" />
              <path :d="pieSlice(partsPercent + laborPercent, vatPercent)" fill="#f59e0b" />
              <circle cx="18" cy="18" r="8" fill="white" class="dark:fill-slate-900" />
            </svg>
            <div class="space-y-1.5 text-xs">
              <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-blue-500 flex-shrink-0"></span><span class="text-gray-700 dark:text-slate-300">قطع الغيار: <b>{{ partsPercent }}%</b></span></div>
              <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span><span class="text-gray-700 dark:text-slate-300">العمالة: <b>{{ laborPercent }}%</b></span></div>
              <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-amber-500 flex-shrink-0"></span><span class="text-gray-700 dark:text-slate-300">الضريبة: <b>{{ vatPercent }}%</b></span></div>
            </div>
          </div>
        </div>

        <!-- Warranty Tracker -->
        <div v-if="warrantyItems.length" class="px-6 py-4 border-t border-gray-100 dark:border-slate-700">
          <p class="text-xs font-bold text-gray-600 dark:text-slate-300 mb-3 flex items-center gap-1.5">
            <ShieldCheckIcon class="w-4 h-4 text-emerald-500" /> ضمان القطع المستبدلة
          </p>
          <div class="space-y-2">
            <div v-for="w in warrantyItems" :key="w.id" class="flex items-center justify-between bg-gray-50 dark:bg-slate-900 rounded-xl px-3 py-2">
              <div>
                <p class="text-xs font-medium text-gray-800 dark:text-white">{{ w.part_name }}</p>
                <p class="text-xs text-gray-400">ينتهي {{ formatDate(w.warranty_end) }}</p>
              </div>
              <span :class="warrantyBadge(w.status)" class="text-xs px-2 py-0.5 rounded-full font-medium">
                {{ w.days_remaining }} يوم
              </span>
            </div>
          </div>
        </div>

        <!-- Dual QR Section -->
        <div class="grid grid-cols-2 gap-4 px-6 py-4 bg-gray-50 dark:bg-slate-900 border-t border-gray-100 dark:border-slate-700">
          <div class="text-center">
            <div ref="zatcaQrRef" class="bg-white p-2 rounded-xl inline-block shadow-sm"></div>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1.5">QR ضريبي (ZATCA)</p>
          </div>
          <div class="text-center">
            <div class="bg-white p-2 rounded-xl inline-block shadow-sm">
              <div ref="experienceQrRef"></div>
            </div>
            <p class="text-xs text-gray-500 dark:text-slate-400 mt-1.5">تجربتك + صور العمل</p>
          </div>
        </div>

        <!-- Next Service CTA -->
        <div v-if="nextService" class="mx-6 mb-4 mt-2 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-4 text-white">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-bold text-sm">موعدك القادم 📅</p>
              <p class="text-blue-100 text-xs mt-0.5">{{ formatDate(nextService.next_service_date) }}</p>
              <p v-if="nextService.discount_code" class="mt-2 text-xs">
                كود الخصم: <span class="font-mono bg-white/20 px-2 py-0.5 rounded-lg">{{ nextService.discount_code }}</span>
                <span class="text-blue-200 mr-1">({{ nextService.discount_value }}{{ nextService.discount_type === 'percentage' ? '%' : ' ر.س' }})</span>
              </p>
            </div>
            <CalendarDaysIcon class="w-10 h-10 text-white/30 flex-shrink-0" />
          </div>
        </div>

      </div>

      <!-- NPS Rating Card -->
      <div v-if="!npsSubmitted" class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5 text-center shadow-sm">
        <p class="font-bold text-gray-900 dark:text-white mb-1">كيف كانت تجربتك؟</p>
        <p class="text-xs text-gray-400 mb-4">تقييمك يساعدنا على التطوير</p>
        <div class="flex justify-center gap-3">
          <button v-for="n in 5" :key="n" @click="submitNPS(n)"
            :class="npsHover >= n || npsScore >= n ? 'text-yellow-400 scale-110' : 'text-gray-200 dark:text-slate-600'"
            class="text-3xl transition-transform" @mouseenter="npsHover = n" @mouseleave="npsHover = 0">
            ★
          </button>
        </div>
        <p class="text-xs text-gray-400 mt-2">{{ npsScore ? npsLabels[npsScore - 1] : '' }}</p>
      </div>
      <div v-else class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-2xl p-4 text-center">
        <p class="text-green-700 dark:text-green-400 font-medium text-sm">✅ شكراً على تقييمك!</p>
      </div>

      <!-- Green Invoice Toggle -->
      <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-2xl p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-2xl">🌿</span>
            <div>
              <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">فاتورة خضراء</p>
              <p class="text-xs text-emerald-600 dark:text-emerald-400">استلم عبر واتساب واكسب 10 نقاط ولاء</p>
            </div>
          </div>
          <button @click="shareWhatsApp"
            class="text-xs bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-xl transition-colors">
            إرسال
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ChevronRightIcon, PrinterIcon, ArrowDownTrayIcon,
  ShieldCheckIcon, CalendarDaysIcon
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const route   = useRoute()
const router  = useRouter()
const loading = ref(true)
const invoice     = ref<any>(null)
const warrantyItems = ref<any[]>([])
const nextService   = ref<any>(null)
const zatcaQrRef     = ref<HTMLElement | null>(null)
const experienceQrRef= ref<HTMLElement | null>(null)
const npsScore    = ref(0)
const npsHover    = ref(0)
const npsSubmitted= ref(false)
const npsLabels   = ['سيء جداً', 'سيء', 'مقبول', 'جيد', 'ممتاز']

const fmt = (v: number) => Number(v || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'

const statusLabel = (s: string) => ({'paid':'مدفوعة','partial':'مدفوعة جزئياً','unpaid':'غير مدفوعة','cancelled':'ملغاة','draft':'مسودة'}[s] ?? s)
const statusClass = (s: string) => ({'paid':'bg-green-100 text-green-700','partial':'bg-amber-100 text-amber-700','unpaid':'bg-red-100 text-red-700','cancelled':'bg-gray-100 text-gray-500','draft':'bg-blue-100 text-blue-700'}[s] ?? 'bg-gray-100 text-gray-500')
const warrantyBadge = (s: string) => ({'active':'bg-green-100 text-green-700','warning':'bg-amber-100 text-amber-700','expiring_soon':'bg-orange-100 text-orange-700','expired':'bg-red-100 text-red-600'}[s] ?? 'bg-gray-100')

const partsPercent = computed(() => {
  if (!invoice.value) return 33
  const subtotal = invoice.value.subtotal || 1
  const parts = (invoice.value.items || []).filter((i: any) => i.item_type !== 'service').reduce((s: number, i: any) => s + Number(i.subtotal), 0)
  return Math.round((parts / subtotal) * 100) || 40
})
const laborPercent = computed(() => Math.max(5, 100 - partsPercent.value - vatPercent.value))
const vatPercent   = computed(() => {
  if (!invoice.value) return 15
  return Math.round(((invoice.value.tax_amount || 0) / (invoice.value.total || 1)) * 100)
})

function pieSlice(startPct: number, pct: number) {
  const cx = 18, cy = 18, r = 16
  const startAngle = (startPct / 100) * 2 * Math.PI - Math.PI / 2
  const endAngle   = ((startPct + pct) / 100) * 2 * Math.PI - Math.PI / 2
  const x1 = cx + r * Math.cos(startAngle), y1 = cy + r * Math.sin(startAngle)
  const x2 = cx + r * Math.cos(endAngle),   y2 = cy + r * Math.sin(endAngle)
  const large = pct > 50 ? 1 : 0
  return `M${cx},${cy} L${x1},${y1} A${r},${r} 0 ${large},1 ${x2},${y2} Z`
}

async function generateQRs() {
  await nextTick()
  try {
    const QRCode = (await import('qrcode')).default
    const inv = invoice.value
    // ZATCA QR data (simplified TLV)
    const zatcaData = btoa(unescape(encodeURIComponent(
      `${inv.company?.name ?? ''}\n${inv.company?.vat_number ?? ''}\n${inv.issued_at}\n${inv.total}\n${inv.tax_amount}`
    )))
    if (zatcaQrRef.value) {
      const canvas = document.createElement('canvas')
      await QRCode.toCanvas(canvas, zatcaData, { width: 100, margin: 1 })
      zatcaQrRef.value.innerHTML = ''
      zatcaQrRef.value.appendChild(canvas)
    }
    const expUrl = `${window.location.origin}/invoice-review/${inv.uuid}`
    if (experienceQrRef.value) {
      const canvas2 = document.createElement('canvas')
      await QRCode.toCanvas(canvas2, expUrl, { width: 100, margin: 1 })
      experienceQrRef.value.innerHTML = ''
      experienceQrRef.value.appendChild(canvas2)
    }
  } catch (e) { /* qrcode not installed - silently skip */ }
}

async function submitNPS(score: number) {
  npsScore.value = score
  try {
    await apiClient.post('/nps', { score, invoice_id: invoice.value?.id, channel: 'invoice' })
    npsSubmitted.value = true
  } catch { npsSubmitted.value = true }
}

function shareWhatsApp() {
  const msg = `فاتورة رقم ${invoice.value?.invoice_number} - الإجمالي: ${fmt(invoice.value?.total)} ر.س\nرابط العرض: ${window.location.href}`
  const phone = invoice.value?.customer?.phone?.replace(/\D/g,'') ?? ''
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank')
}

function printInvoice() {
  window.print()
}

async function downloadPDF() {
  try {
    const { default: jsPDF } = await import('jspdf')
    const doc = new jsPDF({ orientation: 'portrait', format: 'a4' })
    doc.text(`Invoice: ${invoice.value?.invoice_number}`, 20, 20)
    doc.text(`Total: ${invoice.value?.total} SAR`, 20, 30)
    doc.save(`invoice-${invoice.value?.invoice_number}.pdf`)
  } catch { alert('جارٍ تطوير ميزة التصدير') }
}

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const { data } = await apiClient.get(`/invoices/${id}`)
    invoice.value = data.data ?? data
    // Fetch warranty items
    try {
      const wr = await apiClient.get(`/warranty-items?invoice_id=${id}`)
      warrantyItems.value = wr.data.data?.data ?? []
    } catch {}
    // Fetch next service
    try {
      if (invoice.value?.customer?.id) {
        const sr = await apiClient.get(`/service-reminders?customer_id=${invoice.value.customer.id}`)
        const items = sr.data.data?.data ?? []
        nextService.value = items.find((x: any) => !x.notified) ?? null
      }
    } catch {}
    await generateQRs()
  } catch { /* error handled */ } finally { loading.value = false }
}

onMounted(load)
</script>

<style>
@media print {
  body > *:not(#invoice-print-area) { display: none; }
  #invoice-print-area { display: block; page-break-inside: avoid; }
}
</style>
