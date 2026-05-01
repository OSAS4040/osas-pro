<template>
  <div class="app-shell-page space-y-6" dir="rtl">
    <div class="page-head">
      <div class="page-title-wrap">
        <h1 class="page-title-xl">{{ l('مركز التنفيذ والبحث', 'Execution & lookup hub') }}</h1>
        <p class="page-subtitle">
          {{
            l(
              'أدخل رقم أمر العمل، أو لوحة بحروف وأرقام منفصلة، أو استخدم الكاميرا لمسح باركود/QR (أمر عمل أو مركبة) أو التقاط صورة للوحة. تُعرض بيانات المركبة وأمر العمل النشط والرصيد عند التوفر.',
              'Enter a work order, or plate letters/digits, or use the camera for barcode/QR (work order or vehicle) or a plate photo. Vehicle, active work order, and balance appear when available.',
            )
          }}
        </p>
      </div>
    </div>

    <div class="rounded-2xl border border-gray-200/90 bg-white/90 p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/60">
      <!-- رقم أمر العمل -->
      <div class="space-y-2">
        <label class="label">{{ l('رقم أمر العمل', 'Work order number') }}</label>
        <input
          v-model="orderQuery"
          type="text"
          class="field w-full font-mono"
          :placeholder="l('مثال: WO-2026-0001', 'e.g. WO-2026-0001')"
          autocomplete="off"
          @keydown.enter.prevent="runLookup"
        />
      </div>

      <!-- لوحة المركبة -->
      <div class="mt-6 space-y-2 border-t border-gray-100 pt-5 dark:border-slate-700">
        <label class="label">{{ l('لوحة المركبة (حروف إنجليزية كبيرة + أرقام)', 'Plate (Latin letters + digits)') }}</label>
        <p class="text-xs text-gray-500 dark:text-slate-400">
          {{ l('الحروف تُحوَّل تلقائياً إلى كبيرة. الصيغة: ٣ حروف ثم ٣–٤ أرقام (مثل ABC و 1234).', 'Letters are uppercased. Format: 3 letters and 3–4 digits (e.g. ABC + 1234).') }}
        </p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
          <div class="flex flex-1 flex-wrap items-end gap-3" dir="ltr">
            <div class="min-w-[7rem] flex-1">
              <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-slate-400">{{ l('الحروف (٣)', 'Letters (3)') }}</span>
              <input
                v-model="plateLetters"
                type="text"
                maxlength="3"
                inputmode="text"
                autocapitalize="characters"
                class="field w-full text-center font-mono text-lg font-semibold uppercase tracking-widest"
                placeholder="ABC"
                @input="onPlateLettersInput"
                @keydown.enter.prevent="runLookup"
              />
            </div>
            <div class="min-w-[7rem] flex-1">
              <span class="mb-1 block text-[11px] font-medium text-gray-500 dark:text-slate-400">{{ l('الأرقام (٣–٤)', 'Digits (3–4)') }}</span>
              <input
                v-model="plateDigits"
                type="text"
                maxlength="4"
                inputmode="numeric"
                class="field w-full text-center font-mono text-lg font-semibold tracking-wide"
                placeholder="1234"
                @input="onPlateDigitsInput"
                @keydown.enter.prevent="runLookup"
              />
            </div>
          </div>
          <button
            type="button"
            class="btn btn-primary shrink-0 px-6 py-2.5 text-sm font-semibold disabled:opacity-50"
            :disabled="loading || !canLookup"
            @click="runLookup"
          >
            {{ loading ? l('جارٍ البحث…', 'Searching…') : l('بحث', 'Search') }}
          </button>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4 dark:border-slate-700">
        <CameraIntakeScanner @plate="onPlateScanned" @order="onOrderFromScan" />
        <span class="text-xs text-gray-500 dark:text-slate-400">
          {{
            l(
              'كاميرا واحدة: باركود/QR لأمر العمل أو مركبة، أو التقاط صورة للوحة (OCR)',
              'One camera: barcode/QR for work order or vehicle, or plate photo (OCR)',
            )
          }}
        </span>
      </div>
      <p v-if="errorMsg" class="mt-3 text-sm text-red-600 dark:text-red-400">{{ errorMsg }}</p>
    </div>

    <div v-if="payload" class="grid gap-4 md:grid-cols-3">
      <div class="rounded-2xl border border-gray-200/90 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
        <div class="text-xs font-semibold uppercase text-gray-400">{{ l('المركبة', 'Vehicle') }}</div>
        <template v-if="payload.vehicle">
          <p class="mt-2 font-mono text-xl font-bold tracking-wide text-gray-900 dark:text-slate-100">
            {{ payload.vehicle.plate_number }}
          </p>
          <p class="text-sm text-gray-600 dark:text-slate-300">
            {{ [payload.vehicle.make, payload.vehicle.model].filter(Boolean).join(' ') }}
          </p>
        </template>
        <p v-else class="mt-2 text-sm text-amber-700 dark:text-amber-300">{{ l('لا توجد مركبة مطابقة', 'No matching vehicle') }}</p>
      </div>

      <div class="rounded-2xl border border-gray-200/90 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
        <div class="text-xs font-semibold uppercase text-gray-400">{{ l('أمر العمل', 'Work order') }}</div>
        <template v-if="payload.work_order">
          <p class="mt-2 font-semibold text-gray-900 dark:text-slate-100">{{ payload.work_order.order_number }}</p>
          <p class="mt-1 text-xs text-gray-500">{{ payload.work_order.status }}</p>
          <p class="mt-2 text-xs" :class="payload.work_order.is_active ? 'text-green-600' : 'text-gray-500'">
            {{ payload.work_order.is_active ? l('نشط', 'Active') : l('غير نشط', 'Not active') }}
          </p>
          <RouterLink
            v-if="payload.work_order.id"
            :to="{ name: 'work-orders.show', params: { id: String(payload.work_order.id) } }"
            class="mt-3 inline-flex text-sm font-semibold text-primary-600 hover:underline"
          >
            {{ l('فتح أمر العمل', 'Open work order') }} →
          </RouterLink>
        </template>
        <p v-else class="mt-2 text-sm text-gray-500">{{ l('لا يوجد أمر عمل مطابق', 'No matching work order') }}</p>
      </div>

      <div class="rounded-2xl border border-gray-200/90 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/50">
        <div class="text-xs font-semibold uppercase text-gray-400">{{ l('الرصيد والتنفيذ', 'Balance & execution') }}</div>
        <dl class="mt-2 space-y-1 text-sm text-gray-700 dark:text-slate-300">
          <div class="flex justify-between gap-2">
            <dt>{{ l('محفظة أسطول', 'Fleet wallet') }}</dt>
            <dd class="font-mono font-semibold">{{ formatMoney(payload.prepaid?.fleet_main_balance) }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt>{{ l('محفظة مركبة', 'Vehicle wallet') }}</dt>
            <dd class="font-mono font-semibold">{{ formatMoney(payload.prepaid?.vehicle_wallet_balance) }}</dd>
          </div>
          <div class="flex justify-between gap-2">
            <dt>{{ l('رصيد عميل', 'Customer wallet') }}</dt>
            <dd class="font-mono font-semibold">{{ formatMoney(payload.prepaid?.customer_main_balance) }}</dd>
          </div>
        </dl>
        <p v-if="payload.execution" class="mt-3 text-xs text-gray-500">
          {{
            payload.execution.can_execute_now
              ? l('يمكن المتابعة لتنفيذ الخدمة حسب صلاحياتك.', 'You may proceed to execute per your permissions.')
              : l('تحقق من حالة الأمر أو الرصيد قبل التنفيذ.', 'Check work order status or balance before execution.')
          }}
        </p>
      </div>
    </div>

    <div class="rounded-2xl border border-gray-200/90 bg-white p-5 dark:border-slate-700 dark:bg-slate-900/50">
      <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100">{{ l('قراءة العداد (مساعدة)', 'Odometer assist') }}</h2>
      <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">
        {{
          l(
            'صوّر عدّاد المركبة؛ يُستخرج رقم تقريبي (راجع قبل الحفظ داخل أمر العمل).',
            'Photograph the odometer; a number is suggested (verify before saving on the work order).',
          )
        }}
      </p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <input ref="odoFileRef" type="file" accept="image/*" capture="environment" class="hidden" @change="onOdoFile" />
        <button type="button" class="btn btn-secondary text-sm" @click="odoFileRef?.click()">
          {{ l('اختيار / تصوير العداد', 'Pick / snap odometer') }}
        </button>
        <span v-if="odoLoading" class="text-xs text-gray-500">{{ l('جارٍ التحليل…', 'Analyzing…') }}</span>
      </div>
      <p v-if="odoError" class="mt-2 text-xs text-red-600">{{ odoError }}</p>
      <div v-if="odoSuggestion !== null" class="mt-3 rounded-xl bg-primary-50/80 p-3 text-sm dark:bg-primary-950/30">
        <span class="text-gray-600 dark:text-slate-400">{{ l('القراءة المقترحة:', 'Suggested reading:') }}</span>
        <span class="mr-2 font-mono text-lg font-bold text-primary-800 dark:text-primary-200">{{ odoSuggestion }}</span>
        <span v-if="odoConfidence !== null" class="text-xs text-gray-500">({{ Math.round(odoConfidence * 100) }}%)</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue'
import CameraIntakeScanner from '@/components/CameraIntakeScanner.vue'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'

const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const orderQuery = ref('')
const plateLetters = ref('')
const plateDigits = ref('')

const loading = ref(false)
const errorMsg = ref('')
const payload = ref<any>(null)

const odoFileRef = ref<HTMLInputElement | null>(null)
const odoLoading = ref(false)
const odoError = ref('')
const odoSuggestion = ref<number | null>(null)
const odoConfidence = ref<number | null>(null)

const canLookup = computed(() => {
  if (orderQuery.value.trim() !== '') return true
  const L = normalizedPlateLetters(plateLetters.value)
  const D = normalizedPlateDigits(plateDigits.value)
  return L.length === 3 && D.length >= 3 && D.length <= 4
})

function normalizedPlateLetters(raw: string): string {
  return String(raw ?? '')
    .toUpperCase()
    .replace(/[^A-Z]/g, '')
    .slice(0, 3)
}

function normalizedPlateDigits(raw: string): string {
  return String(raw ?? '')
    .replace(/\D/g, '')
    .slice(0, 4)
}

function onPlateLettersInput() {
  plateLetters.value = normalizedPlateLetters(plateLetters.value)
}

function onPlateDigitsInput() {
  plateDigits.value = normalizedPlateDigits(plateDigits.value)
}

function buildLookupParams(): { order_number?: string; plate_number?: string } {
  const ord = orderQuery.value.trim()
  if (ord !== '') {
    return { order_number: ord }
  }
  const L = normalizedPlateLetters(plateLetters.value)
  const D = normalizedPlateDigits(plateDigits.value)
  if (L.length === 3 && D.length >= 3 && D.length <= 4) {
    return { plate_number: `${L} ${D}` }
  }
  return {}
}

function formatMoney(v: unknown): string {
  const n = typeof v === 'number' ? v : Number(v)
  if (Number.isNaN(n)) return '—'
  return n.toLocaleString('ar-SA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function runLookup() {
  errorMsg.value = ''
  payload.value = null
  const params = buildLookupParams()
  if (!params.order_number && !params.plate_number) {
    errorMsg.value = l('أدخل رقم أمر عمل أو لوحة كاملة (٣ حروف + ٣–٤ أرقام).', 'Enter a work order or a full plate (3 letters + 3–4 digits).')
    return
  }
  loading.value = true
  try {
    const { data } = await apiClient.get('/work-orders/intake-lookup', { params })
    payload.value = data?.data ?? data
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message ?? l('تعذّر البحث', 'Lookup failed')
  } finally {
    loading.value = false
  }
}

function applyPlatePartsFromString(p: string) {
  const compact = String(p ?? '')
    .toUpperCase()
    .replace(/\s+/g, '')
    .replace(/[^A-Z0-9]/g, '')
  const m = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (m) {
    plateLetters.value = m[1] ?? ''
    plateDigits.value = m[2] ?? ''
  }
}

function onPlateScanned(p: string) {
  orderQuery.value = ''
  applyPlatePartsFromString(p)
  nextTick(() => runLookup())
}

function extractWorkOrderFromScan(text: string): string {
  const t = String(text ?? '').trim()
  if (!t) return ''
  const pickWo = (s: string) => {
    const m = s.match(/\b(WO[-A-Z0-9]+)\b/i)
    return m?.[1] ? m[1].toUpperCase() : ''
  }
  const direct = pickWo(t)
  if (direct) return direct
  try {
    const u = new URL(t)
    const blob = `${u.pathname}${u.search}${u.hash}`
    const fromUrl = pickWo(blob)
    if (fromUrl) return fromUrl
  } catch {
    /* ليس URL */
  }
  return t
}

function onOrderFromScan(code: string) {
  const c = extractWorkOrderFromScan(code)
  if (!c) return
  orderQuery.value = c
  plateLetters.value = ''
  plateDigits.value = ''
  nextTick(() => runLookup())
}

async function onOdoFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  odoError.value = ''
  odoSuggestion.value = null
  odoConfidence.value = null
  odoLoading.value = true
  try {
    const b64 = await fileToBase64(file)
    const { data } = await apiClient.post('/work-orders/intake-odometer-ocr', { image: b64 })
    const d = data?.data ?? data
    odoSuggestion.value = typeof d?.suggested_reading === 'number' ? d.suggested_reading : null
    odoConfidence.value = typeof d?.confidence === 'number' ? d.confidence : null
    if (odoSuggestion.value === null) {
      odoError.value = l('لم يُعثر على أرقام واضحة.', 'No clear digits found.')
    }
  } catch (e: any) {
    odoError.value = e?.response?.data?.message ?? l('فشل التحليل', 'OCR failed')
  } finally {
    odoLoading.value = false
  }
}

function fileToBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const r = new FileReader()
    r.onload = () => resolve(String(r.result ?? ''))
    r.onerror = () => reject(new Error('read'))
    r.readAsDataURL(file)
  })
}
</script>
