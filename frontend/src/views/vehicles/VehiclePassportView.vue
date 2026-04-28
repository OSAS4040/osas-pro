<template>
  <div class="space-y-5" dir="rtl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button class="p-2 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700" @click="$router.back()">
          <ChevronRightIcon class="w-5 h-5 text-gray-400" />
        </button>
        <div>
          <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="font-mono text-blue-600 dark:text-blue-400">{{ vehicle?.plate_number }}</span>
            <span class="text-gray-400 text-base font-normal">{{ vehicle?.make }} {{ vehicle?.model }}</span>
          </h2>
          <p class="text-xs text-gray-400">جواز المركبة الرقمي</p>
        </div>
      </div>
      <div class="flex flex-col items-end gap-0.5">
        <button
          type="button"
          class="btn btn-outline text-xs py-1.5 px-3 inline-flex items-center justify-center gap-1 disabled:opacity-60"
          :disabled="passportPdfExporting || !vehicle"
          @click="exportPDF"
        >
          <span
            v-if="passportPdfExporting"
            class="inline-block w-3.5 h-3.5 border-2 border-current border-t-transparent rounded-full animate-spin"
            aria-hidden="true"
          />
          <ArrowDownTrayIcon v-else class="w-4 h-4" />
          {{ passportPdfExporting ? 'جاري التصدير…' : 'تصدير PDF' }}
        </button>
        <span class="text-[10px] text-gray-500 dark:text-slate-400 max-w-[14rem] text-right leading-snug">
          إن فشل التصدير البرمجي، استخدم «طباعة» ثم «حفظ كـ PDF» من المتصفح للصفحة الكاملة.
        </span>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <div class="w-8 h-8 border-4 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="vehicle" class="space-y-4">
      <!-- Vehicle Info Card -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
          <div v-for="f in vehicleFields" :key="f.label">
            <p class="text-xs text-gray-400 mb-0.5">{{ f.label }}</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ f.value || '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Oil Change Tracker -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <span class="text-lg">🔧</span> متابعة تغيير الزيت
        </h3>
        <div class="flex items-center gap-3 mb-2">
          <span class="text-xs text-gray-500">آخر تغيير: {{ lastOilChange || 'غير مسجل' }}</span>
          <span class="mx-auto"></span>
          <span class="text-xs text-gray-500">الكيلومتر التالي: {{ nextOilKm || '—' }}</span>
        </div>
        <div class="w-full bg-gray-100 dark:bg-slate-700 rounded-full h-3">
          <div class="h-3 rounded-full transition-all duration-500"
               :class="oilProgress > 80 ? 'bg-red-500' : oilProgress > 60 ? 'bg-amber-500' : 'bg-green-500'"
               :style="`width:${oilProgress}%`"
          ></div>
        </div>
        <p class="text-xs text-gray-400 mt-1 text-left">{{ oilProgress }}% مكتمل</p>
      </div>

      <!-- Service History Timeline -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <ClipboardDocumentListIcon class="w-4 h-4 text-blue-500" /> سجل الخدمات
        </h3>
        <div v-if="!workOrders.length" class="text-center py-6 text-gray-400 text-xs">لا توجد سجلات خدمة</div>
        <div v-else class="relative">
          <div class="absolute right-4 top-0 bottom-0 w-0.5 bg-gray-100 dark:bg-slate-700"></div>
          <div v-for="wo in workOrders" :key="wo.id" class="flex items-start gap-3 pb-4 relative pr-10">
            <div class="absolute right-2.5 w-3 h-3 rounded-full border-2 border-white dark:border-slate-800 mt-1"
                 :class="workOrderStatusTimelineDotClass(wo.status)"
            >
            </div>
            <div class="flex-1 bg-gray-50 dark:bg-slate-900 rounded-xl p-3">
              <div class="flex justify-between items-start">
                <p class="text-xs font-semibold text-gray-800 dark:text-white">{{ wo.title || 'أمر عمل #' + wo.id }}</p>
                <span class="text-xs text-gray-400">{{ formatDate(wo.created_at) }}</span>
              </div>
              <p class="text-xs text-gray-500 dark:text-slate-400 mt-0.5">{{ wo.description || '' }}</p>
              <div class="flex items-center gap-2 mt-1.5">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="workOrderStatusBadgeClass(wo.status)">
                  {{ workOrderStatusLabel(wo.status) }}
                </span>
                <span v-if="wo.total_amount" class="text-xs text-gray-500">{{ fmt(wo.total_amount) }} ر.س</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Invoice History -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <DocumentTextIcon class="w-4 h-4 text-primary-500" /> سجل الفواتير
        </h3>
        <div v-if="!invoices.length" class="text-center py-4 text-gray-400 text-xs">لا توجد فواتير</div>
        <div v-else class="space-y-2">
          <div v-for="inv in invoices" :key="inv.id"
               class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-900 rounded-xl"
          >
            <div>
              <p class="text-xs font-mono font-semibold text-gray-800 dark:text-white">#{{ inv.invoice_number }}</p>
              <p class="text-xs text-gray-400">{{ formatDate(inv.issued_at) }}</p>
            </div>
            <div class="text-right">
              <p class="text-sm font-bold text-gray-800 dark:text-white">{{ fmt(inv.total) }} ر.س</p>
              <span class="text-xs px-2 py-0.5 rounded-full"
                    :class="inv.status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
              >
                {{ inv.status === 'paid' ? 'مدفوعة' : 'غير مدفوعة' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Vehicle Settings -->
      <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 p-5">
        <h3 class="font-bold text-sm text-gray-800 dark:text-white mb-4 flex items-center gap-2">
          <CogIcon class="w-4 h-4 text-gray-500" /> إعدادات المركبة
        </h3>
        <div v-if="!editingSettings" class="grid grid-cols-2 gap-3">
          <div v-for="s in settingsFields" :key="s.key">
            <p class="text-xs text-gray-400">{{ s.label }}</p>
            <p class="text-sm font-medium text-gray-800 dark:text-white mt-0.5">{{ settings[s.key] || '—' }}</p>
          </div>
          <div class="col-span-2">
            <button class="text-xs text-blue-600 hover:underline" @click="editingSettings = true">تعديل الإعدادات</button>
          </div>
        </div>
        <div v-else class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div v-for="s in settingsFields" :key="s.key">
              <label class="block text-xs text-gray-400 mb-1">{{ s.label }}</label>
              <input v-model="settings[s.key]" class="field text-sm" :placeholder="s.placeholder" />
            </div>
          </div>
          <div class="flex gap-2">
            <button class="btn btn-primary text-xs py-1.5 px-3" @click="saveSettings">حفظ</button>
            <button class="btn btn-outline text-xs py-1.5 px-3" @click="editingSettings = false">إلغاء</button>
          </div>
        </div>
      </div>

      <!-- قالب مخفي لتصدير PDF عربي (html2canvas + jsPDF — يتفادى تشويه خط jsPDF الافتراضي) -->
      <div
        v-if="vehicle"
        id="vehicle-passport-print-template"
        class="passport-print-only"
        dir="rtl"
        aria-hidden="true"
      >
        <div class="passport-print-sheet">
          <header class="passport-print-head">
            <div>
              <h1 class="passport-print-title">جواز المركبة الرقمي</h1>
              <p class="passport-print-plate">{{ vehicle.plate_number || '—' }}</p>
              <p class="passport-print-sub">
                {{ [vehicle.make, vehicle.model].filter(Boolean).join(' ') || '—' }}
                <span v-if="vehicle.year"> · {{ vehicle.year }}</span>
              </p>
            </div>
            <div class="passport-print-status" :class="passportPrintStatusClass">
              {{ passportPrintStatusLabel }}
            </div>
          </header>

          <section class="passport-print-section">
            <h2>بيانات المركبة</h2>
            <table class="passport-print-table">
              <tbody>
                <tr v-for="f in vehicleFields" :key="f.label">
                  <th>{{ f.label }}</th>
                  <td>{{ f.value || '—' }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section class="passport-print-section">
            <h2>متابعة الزيت</h2>
            <p class="passport-print-p">
              آخر تغيير: <strong>{{ lastOilChange || 'غير مسجل' }}</strong>
              — الكيلومتر التالي: <strong>{{ nextOilKm || '—' }}</strong>
              — التقدم: <strong>{{ oilProgress }}٪</strong>
            </p>
          </section>

          <section class="passport-print-section">
            <h2>سجل أوامر العمل (آخر {{ workOrders.length }})</h2>
            <table v-if="workOrders.length" class="passport-print-table passport-print-wo">
              <thead>
                <tr>
                  <th>الوصف</th>
                  <th>التاريخ</th>
                  <th>الحالة</th>
                  <th>المبلغ</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="wo in workOrders" :key="wo.id">
                  <td>{{ wo.title || ('أمر عمل #' + wo.id) }}</td>
                  <td>{{ formatDate(wo.created_at) }}</td>
                  <td>{{ workOrderStatusLabel(wo.status) }}</td>
                  <td>{{ wo.total_amount != null ? fmt(wo.total_amount) + ' ر.س' : '—' }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="passport-print-muted">لا توجد سجلات.</p>
          </section>

          <section class="passport-print-section">
            <h2>سجل الفواتير (آخر {{ invoices.length }})</h2>
            <table v-if="invoices.length" class="passport-print-table">
              <thead>
                <tr>
                  <th>رقم الفاتورة</th>
                  <th>التاريخ</th>
                  <th>المجموع</th>
                  <th>الحالة</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="inv in invoices" :key="inv.id">
                  <td>#{{ inv.invoice_number }}</td>
                  <td>{{ formatDate(inv.issued_at) }}</td>
                  <td>{{ fmt(inv.total) }} ر.س</td>
                  <td>{{ inv.status === 'paid' ? 'مدفوعة' : 'غير مدفوعة' }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="passport-print-muted">لا توجد فواتير.</p>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { ChevronRightIcon, ArrowDownTrayIcon, ClipboardDocumentListIcon, DocumentTextIcon, CogIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { PDF_EXPORT_FAIL_AR } from '@/constants/pdfExportMessages'
import { ensurePrintFontsReady } from '@/composables/useAppPrint'
import { workOrderStatusLabel, workOrderStatusBadgeClass, workOrderStatusTimelineDotClass } from '@/utils/workOrderStatusLabels'

const toast = useToast()

const route  = useRoute()
const loading = ref(true)
const vehicle = ref<any>(null)
const workOrders = ref<any[]>([])
const invoices   = ref<any[]>([])
const editingSettings = ref(false)
const passportPdfExporting = ref(false)

const settings = reactive<Record<string, string>>({
  oil_type: '', oil_capacity: '', battery_capacity: '', tire_size_front: '',
  tire_size_rear: '', last_odometer: '', next_oil_change_km: '',
  air_filter_interval: '', coolant_type: '',
})

const settingsFields = [
  { key: 'oil_type',          label: 'نوع الزيت',          placeholder: '5W-30' },
  { key: 'oil_capacity',      label: 'سعة الزيت (لتر)',     placeholder: '4.5' },
  { key: 'battery_capacity',  label: 'سعة البطارية (Ah)',   placeholder: '70' },
  { key: 'tire_size_front',   label: 'حجم الكفر الأمامي',  placeholder: '225/60R17' },
  { key: 'tire_size_rear',    label: 'حجم الكفر الخلفي',   placeholder: '225/60R17' },
  { key: 'last_odometer',     label: 'آخر قراءة عداد (كم)', placeholder: '50000' },
  { key: 'next_oil_change_km',label: 'تغيير الزيت القادم', placeholder: '55000' },
  { key: 'coolant_type',      label: 'نوع سائل التبريد',   placeholder: 'OAT' },
]

const vehicleFields = computed(() => [
  { label: 'رقم اللوحة',    value: vehicle.value?.plate_number },
  { label: 'الماركة',       value: vehicle.value?.make },
  { label: 'الموديل',       value: vehicle.value?.model },
  { label: 'سنة الصنع',     value: vehicle.value?.year },
  { label: 'اللون',         value: vehicle.value?.color },
  { label: 'الوقود',        value: fuelLabel(vehicle.value?.fuel_type) },
  { label: 'رقم الشاسيه',  value: vehicle.value?.vin },
  { label: 'الكيلومتر',     value: settings.last_odometer ? settings.last_odometer + ' كم' : '—' },
])

const lastOilChange = computed(() => {
  const wo = [...workOrders.value].reverse().find(w => w.title?.includes('زيت') || w.description?.includes('زيت'))
  return wo ? formatDate(wo.created_at) : null
})
const nextOilKm  = computed(() => settings.next_oil_change_km)
const oilProgress = computed(() => {
  if (!settings.last_odometer || !settings.next_oil_change_km) return 0
  const interval = 5000
  const lastChange = Number(settings.next_oil_change_km) - interval
  const driven = Number(settings.last_odometer) - lastChange
  return Math.min(100, Math.round((driven / interval) * 100))
})

/** أمر عمل «نشط» في الورشة (للعرض في ملف PDF) */
const hasActiveWorkOrderForPrint = computed(() =>
  workOrders.value.some((w) => ['in_progress', 'assigned'].includes(String(w.status))),
)

const passportPrintStatusLabel = computed(() => {
  if (hasActiveWorkOrderForPrint.value) return 'أمر عمل نشط'
  const pending = workOrders.value.some((w) => ['pending', 'new'].includes(String(w.status)))
  if (pending) return 'في انتظار المعالجة'
  return 'لا يوجد أمر عمل نشط'
})

const passportPrintStatusClass = computed(() => {
  if (hasActiveWorkOrderForPrint.value) return 'passport-print-status--active'
  const pending = workOrders.value.some((w) => ['pending', 'new'].includes(String(w.status)))
  if (pending) return 'passport-print-status--pending'
  return 'passport-print-status--idle'
})

const fmt = (v: number) => Number(v || 0).toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const formatDate = (d: string) => d ? new Date(d).toLocaleDateString('ar-SA') : '—'
const fuelLabel = (f: string) => ({'gasoline':'بنزين','diesel':'ديزل','hybrid':'هجين','electric':'كهرباء'}[f] ?? f)

async function saveSettings() {
  const vid = route.params.id
  if (!vid) return
  try {
    await apiClient.put(`/vehicles/${vid}/settings`, {
      oil_type: settings.oil_type || null,
      oil_capacity_liters: settings.oil_capacity || null,
      battery_capacity_ah: settings.battery_capacity || null,
      custom_settings: {
        tire_size_front: settings.tire_size_front,
        tire_size_rear: settings.tire_size_rear,
        last_odometer: settings.last_odometer,
        next_oil_change_km: settings.next_oil_change_km,
        air_filter_interval: settings.air_filter_interval,
        coolant_type: settings.coolant_type,
      },
    })
    editingSettings.value = false
  } catch {
    editingSettings.value = false
  }
}

async function exportPDF() {
  if (!vehicle.value || passportPdfExporting.value) return
  passportPdfExporting.value = true
  let captureNode: HTMLElement | null = null
  try {
    const target = document.getElementById('vehicle-passport-print-template')
    if (!target) {
      throw new Error('passport print root not found')
    }

    const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
      import('html2canvas'),
      import('jspdf'),
    ])

    captureNode = target.cloneNode(true) as HTMLElement
    captureNode.classList.remove('passport-print-only')
    captureNode.style.display = 'block'
    captureNode.style.position = 'fixed'
    captureNode.style.left = '-10000px'
    captureNode.style.top = '0'
    captureNode.style.zIndex = '-1'
    document.body.appendChild(captureNode)

    await ensurePrintFontsReady()

    const sheet = captureNode.querySelector('.passport-print-sheet') as HTMLElement | null
    const captureEl = sheet ?? captureNode

    const canvas = await html2canvas(captureEl, {
      scale: 2,
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#ffffff',
      imageTimeout: 20000,
      logging: false,
    })

    if (captureNode.parentNode) captureNode.parentNode.removeChild(captureNode)
    captureNode = null

    const imgData = canvas.toDataURL('image/png')
    if (!imgData || imgData.length < 100) {
      throw new Error('empty canvas')
    }

    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' })
    const pageW = pdf.internal.pageSize.getWidth()
    const pageH = pdf.internal.pageSize.getHeight()
    const imgW = pageW
    const imgH = (canvas.height * imgW) / canvas.width

    let heightLeft = imgH
    let position = 0
    pdf.addImage(imgData, 'PNG', 0, position, imgW, imgH)
    heightLeft -= pageH
    while (heightLeft > 0) {
      position = heightLeft - imgH
      pdf.addPage()
      pdf.addImage(imgData, 'PNG', 0, position, imgW, imgH)
      heightLeft -= pageH
    }

    const plate = vehicle.value?.plate_number ?? 'vehicle'
    pdf.save(`vehicle-passport-${plate}.pdf`)
    toast.success('تم التصدير', 'تم تنزيل ملف PDF.')
  } catch (e: unknown) {
    console.warn('[VehiclePassport PDF]', e)
    toast.error('تصدير جواز المركبة', PDF_EXPORT_FAIL_AR)
  } finally {
    if (captureNode?.parentNode) {
      captureNode.parentNode.removeChild(captureNode)
    }
    passportPdfExporting.value = false
  }
}

function applyVehicleSettingsPayload(vs: Record<string, unknown>): void {
  settings.oil_type = String(vs.oil_type ?? '')
  settings.oil_capacity = String(vs.oil_capacity_liters ?? '')
  settings.battery_capacity = String(vs.battery_capacity_ah ?? '')
  const cs =
    vs.custom_settings && typeof vs.custom_settings === 'object'
      ? (vs.custom_settings as Record<string, string>)
      : {}
  settings.tire_size_front = String(cs.tire_size_front ?? vs.tire_size ?? '')
  settings.tire_size_rear = String(cs.tire_size_rear ?? '')
  settings.last_odometer = String(cs.last_odometer ?? '')
  settings.next_oil_change_km = String(cs.next_oil_change_km ?? '')
  settings.air_filter_interval = String(cs.air_filter_interval ?? '')
  settings.coolant_type = String(cs.coolant_type ?? '')
}

async function load() {
  loading.value = true
  const vid = route.params.id
  try {
    const { data } = await apiClient.get(`/vehicles/${vid}`)
    vehicle.value = data.data ?? data
    try {
      const sRes = await apiClient.get(`/vehicles/${vid}/settings`)
      const vs = (sRes.data?.data ?? sRes.data) as Record<string, unknown>
      if (vs && typeof vs === 'object') applyVehicleSettingsPayload(vs)
    } catch {
      /* لا يوجد صف إعدادات بعد */
    }
    try {
      const wo = await apiClient.get(`/work-orders?vehicle_id=${vid}&per_page=20`)
      workOrders.value = wo.data?.data?.data ?? wo.data?.data ?? []
    } catch {
      workOrders.value = []
    }
    try {
      const inv = await apiClient.get(`/invoices?vehicle_id=${vid}&per_page=20`)
      invoices.value = inv.data?.data?.data ?? inv.data?.data ?? []
    } catch {
      invoices.value = []
    }
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-xl text-sm focus:ring-2 focus:ring-blue-400 focus:border-transparent dark:bg-slate-700 dark:text-white; }
.btn { @apply inline-flex items-center gap-1 rounded-xl font-medium transition-colors cursor-pointer; }
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white px-3 py-2; }
.btn-outline { @apply border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 px-3 py-2; }
.passport-print-only {
  display: none;
}
</style>

<style>
/* غير scoped: يُطبَّق على استنساخ DOM لـ html2canvas */
#vehicle-passport-print-template .passport-print-sheet {
  font-family: var(--font-sans, system-ui, 'Segoe UI', Tahoma, Arial, sans-serif);
  width: 210mm;
  min-height: 297mm;
  margin: 0 auto;
  padding: 10mm 12mm;
  box-sizing: border-box;
  background: #fff;
  color: #111827;
}
#vehicle-passport-print-template .passport-print-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 14px;
  padding-bottom: 10px;
  border-bottom: 2px solid #e5e7eb;
}
#vehicle-passport-print-template .passport-print-title {
  font-size: 20px;
  margin: 0 0 6px;
  font-weight: 800;
}
#vehicle-passport-print-template .passport-print-plate {
  font-size: 16px;
  font-weight: 700;
  font-family: ui-monospace, monospace;
  margin: 0 0 4px;
}
#vehicle-passport-print-template .passport-print-sub {
  font-size: 12px;
  color: #4b5563;
  margin: 0;
}
#vehicle-passport-print-template .passport-print-status {
  font-size: 11px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 999px;
  white-space: nowrap;
  align-self: center;
}
#vehicle-passport-print-template .passport-print-status--active {
  background: #d1fae5;
  color: #065f46;
}
#vehicle-passport-print-template .passport-print-status--pending {
  background: #fef3c7;
  color: #92400e;
}
#vehicle-passport-print-template .passport-print-status--idle {
  background: #f3f4f6;
  color: #4b5563;
}
#vehicle-passport-print-template .passport-print-section {
  margin-bottom: 12px;
}
#vehicle-passport-print-template .passport-print-section h2 {
  font-size: 12px;
  font-weight: 800;
  margin: 0 0 6px;
  color: #1f2937;
}
#vehicle-passport-print-template .passport-print-p {
  font-size: 10px;
  line-height: 1.5;
  margin: 0;
}
#vehicle-passport-print-template .passport-print-muted {
  font-size: 10px;
  color: #9ca3af;
  margin: 0;
}
#vehicle-passport-print-template .passport-print-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 9px;
}
#vehicle-passport-print-template .passport-print-table th,
#vehicle-passport-print-template .passport-print-table td {
  border: 1px solid #e5e7eb;
  padding: 4px 6px;
  text-align: right;
  vertical-align: top;
}
#vehicle-passport-print-template .passport-print-table th {
  background: #f9fafb;
  font-weight: 700;
  width: 28%;
}
#vehicle-passport-print-template .passport-print-table.passport-print-wo th {
  width: auto;
}
</style>
