<template>
  <div class="space-y-6" dir="rtl">
    <NavigationSourceHint />
    <div class="flex items-center justify-between">
      <div>
        <RouterLink to="/work-orders" class="text-sm text-primary-600 hover:underline">← أوامر العمل</RouterLink>
        <h2 class="text-lg font-semibold text-gray-900 mt-1">
          {{ order?.order_number ?? 'جارٍ التحميل...' }}
        </h2>
      </div>
      <div v-if="order" class="flex gap-2">
        <span :class="statusClass(order.status)" class="px-3 py-1 rounded-full text-xs font-medium">
          {{ statusLabel(order.status) }}
        </span>
        <span :class="priorityClass(order.priority)" class="px-3 py-1 rounded-full text-xs">
          {{ priorityLabel(order.priority) }}
        </span>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">جارٍ التحميل...</div>

    <template v-else-if="order">
      <!-- تفاصيل -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
          <h3 class="font-medium text-gray-800">بيانات العميل والمركبة</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">العميل</dt>
              <dd class="font-medium">{{ order.customer?.name }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">المركبة</dt>
              <dd class="font-mono">{{ order.vehicle?.plate_number }} — {{ order.vehicle?.make }} {{ order.vehicle?.model }}</dd>
            </div>
            <div v-if="order.driver_name" class="flex justify-between">
              <dt class="text-gray-500">السائق</dt>
              <dd>{{ order.driver_name }} {{ order.driver_phone ? `(${order.driver_phone})` : '' }}</dd>
            </div>
            <div v-if="order.odometer_reading" class="flex justify-between">
              <dt class="text-gray-500">العداد</dt>
              <dd>{{ order.odometer_reading }} كم</dd>
            </div>
          </dl>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
          <h3 class="font-medium text-gray-800">معلومات أخرى</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">الفرع</dt>
              <dd>{{ order.branch?.name }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">الفني المسؤول</dt>
              <dd>{{ order.assigned_technician?.name ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">تاريخ الإنشاء</dt>
              <dd>{{ formatDate(order.created_at) }}</dd>
            </div>
            <div v-if="order.started_at" class="flex justify-between">
              <dt class="text-gray-500">تاريخ البدء</dt>
              <dd>{{ formatDate(order.started_at) }}</dd>
            </div>
            <div v-if="order.completed_at" class="flex justify-between">
              <dt class="text-gray-500">تاريخ الإنهاء</dt>
              <dd>{{ formatDate(order.completed_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">الإجمالي التقديري</dt>
              <dd class="font-semibold">{{ Number(order.estimated_total).toFixed(2) }} ر.س</dd>
            </div>
          </dl>
        </div>
      </div>

      <!-- الشكوى / الملاحظات -->
      <div v-if="order.customer_complaint || order.technician_notes || order.diagnosis"
        class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <div v-if="order.customer_complaint">
          <h4 class="text-xs font-semibold text-gray-500 uppercase mb-1">شكوى العميل</h4>
          <p class="text-sm text-gray-700">{{ order.customer_complaint }}</p>
        </div>
        <div v-if="order.diagnosis">
          <h4 class="text-xs font-semibold text-gray-500 uppercase mb-1">التشخيص</h4>
          <p class="text-sm text-gray-700">{{ order.diagnosis }}</p>
        </div>
        <div v-if="order.technician_notes">
          <h4 class="text-xs font-semibold text-gray-500 uppercase mb-1">ملاحظات الفني</h4>
          <p class="text-sm text-gray-700">{{ order.technician_notes }}</p>
        </div>
      </div>

      <!-- البنود -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100">
          <h3 class="font-medium text-gray-800">البنود والخدمات</h3>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
              <th class="px-4 py-3 text-right">الاسم</th>
              <th class="px-4 py-3 text-right">النوع</th>
              <th class="px-4 py-3 text-right">الكمية</th>
              <th class="px-4 py-3 text-right">سعر الوحدة</th>
              <th class="px-4 py-3 text-right">الضريبة</th>
              <th class="px-4 py-3 text-right">الإجمالي</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in order.items" :key="item.id">
              <td class="px-4 py-3 font-medium text-right">{{ item.name }}</td>
              <td class="px-4 py-3 text-gray-500 text-right">{{ itemTypeLabel(item.item_type) }}</td>
              <td class="px-4 py-3 text-right">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right">{{ Number(item.unit_price).toFixed(2) }}</td>
              <td class="px-4 py-3 text-right">{{ Number(item.tax_amount).toFixed(2) }}</td>
              <td class="px-4 py-3 text-right font-semibold">{{ Number(item.total).toFixed(2) }} ر.س</td>
            </tr>
            <tr v-if="!order.items?.length">
              <td colspan="6" class="px-4 py-4 text-center text-gray-400">لا توجد بنود.</td>
            </tr>
          </tbody>
          <tfoot class="border-t-2 border-gray-200 bg-gray-50">
            <tr>
              <td colspan="5" class="px-4 py-3 text-right font-semibold text-gray-700">الإجمالي التقديري</td>
              <td class="px-4 py-3 text-right font-bold text-gray-900">
                {{ Number(order.estimated_total).toFixed(2) }} ر.س
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- تحديث الحالة -->
      <div v-if="allowedTransitions.length" class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-medium text-gray-800 mb-4">تحديث الحالة</h3>
        <div class="flex gap-3 flex-wrap">
          <button
            v-for="t in allowedTransitions"
            :key="t.value"
            :class="t.btnClass"
            class="px-4 py-2 text-sm rounded-lg font-medium transition-colors"
            @click="openTransition(t)"
          >
            {{ t.label }}
          </button>
        </div>
      </div>
    <!-- توقيع السائق -->
    <template v-if="order">
      <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
        <h3 class="font-medium text-gray-800">توقيع السائق</h3>
        <canvas
          ref="signatureCanvas"
          width="600"
          height="160"
          class="w-full border border-gray-300 rounded-lg bg-gray-50 cursor-crosshair touch-none"
          @mousedown="startDraw"
          @mousemove="draw"
          @mouseup="stopDraw"
          @mouseleave="stopDraw"
          @touchstart.prevent="startDrawTouch"
          @touchmove.prevent="drawTouch"
          @touchend="stopDraw"
        ></canvas>
        <div class="flex gap-2">
          <button @click="clearSignature" class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            مسح
          </button>
          <button @click="saveSignature" class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
            حفظ التوقيع
          </button>
          <span v-if="signatureSaved" class="self-center text-xs text-green-600">تم الحفظ ✓</span>
        </div>
      </div>
    </template>

    <!-- نافذة تأكيد التحديث -->
    <Teleport to="body">
      <div v-if="transitionModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50" dir="rtl">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
          <h3 class="font-semibold text-gray-900 mb-4">{{ transitionModal.label }}</h3>
          <div class="space-y-3">
            <div v-if="transitionModal.value === 'completed'">
              <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات الفني</label>
              <textarea v-model="transitionForm.technician_notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div v-if="transitionModal.value === 'completed'">
              <label class="block text-sm font-medium text-gray-700 mb-1">العداد عند الخروج (كم)</label>
              <input v-model="transitionForm.mileage_out" type="number" min="0" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <p v-if="transitionError" class="text-red-600 text-sm mt-2">{{ transitionError }}</p>
          <div class="flex justify-end gap-3 mt-4">
            <button class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50" @click="transitionModal = null">إلغاء</button>
            <button :disabled="transitioning" :class="transitionModal.btnClass" class="px-4 py-2 text-sm rounded-lg font-medium disabled:opacity-50" @click="confirmTransition">
              {{ transitioning ? 'جارٍ التحديث...' : 'تأكيد' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
    </template><!-- /v-else-if="order" -->
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import apiClient from '@/lib/apiClient'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'

const route = useRoute()
const id    = Number(route.params.id)

const order       = ref<any>(null)
const loading     = ref(false)
const transitionModal = ref<any>(null)
const transitioning   = ref(false)
const transitionError = ref('')
const transitionForm  = ref({ technician_notes: '', mileage_out: null as number | null })

// ── Signature ──────────────────────────────────────────────
const signatureCanvas = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const signatureSaved = ref(false)

function getCtx() {
  return signatureCanvas.value?.getContext('2d') ?? null
}
function getPos(e: MouseEvent) {
  const rect = signatureCanvas.value!.getBoundingClientRect()
  const scaleX = signatureCanvas.value!.width / rect.width
  const scaleY = signatureCanvas.value!.height / rect.height
  return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY }
}
function getTouchPos(e: TouchEvent) {
  const rect = signatureCanvas.value!.getBoundingClientRect()
  const scaleX = signatureCanvas.value!.width / rect.width
  const scaleY = signatureCanvas.value!.height / rect.height
  return { x: (e.touches[0].clientX - rect.left) * scaleX, y: (e.touches[0].clientY - rect.top) * scaleY }
}
function startDraw(e: MouseEvent) {
  isDrawing.value = true
  const ctx = getCtx(); if (!ctx) return
  const { x, y } = getPos(e)
  ctx.beginPath(); ctx.moveTo(x, y)
  ctx.lineWidth = 2; ctx.strokeStyle = '#1e293b'; ctx.lineJoin = 'round'; ctx.lineCap = 'round'
}
function draw(e: MouseEvent) {
  if (!isDrawing.value) return
  const ctx = getCtx(); if (!ctx) return
  const { x, y } = getPos(e)
  ctx.lineTo(x, y); ctx.stroke()
}
function startDrawTouch(e: TouchEvent) {
  isDrawing.value = true
  const ctx = getCtx(); if (!ctx) return
  const { x, y } = getTouchPos(e)
  ctx.beginPath(); ctx.moveTo(x, y)
  ctx.lineWidth = 2; ctx.strokeStyle = '#1e293b'; ctx.lineJoin = 'round'; ctx.lineCap = 'round'
}
function drawTouch(e: TouchEvent) {
  if (!isDrawing.value) return
  const ctx = getCtx(); if (!ctx) return
  const { x, y } = getTouchPos(e)
  ctx.lineTo(x, y); ctx.stroke()
}
function stopDraw() { isDrawing.value = false }
function clearSignature() {
  const ctx = getCtx(); if (!ctx || !signatureCanvas.value) return
  ctx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height)
  signatureSaved.value = false
}
function saveSignature() {
  if (!signatureCanvas.value) return
  const dataUrl = signatureCanvas.value.toDataURL('image/png')
  // Store locally (can be extended to upload to API)
  localStorage.setItem(`work_order_${id}_signature`, dataUrl)
  signatureSaved.value = true
}

const STATUS_TRANSITIONS: Record<string, Array<{ value: string; label: string; btnClass: string }>> = {
  draft:       [{ value: 'pending',     label: 'تقديم للمراجعة', btnClass: 'bg-blue-600 text-white hover:bg-blue-700' }],
  pending:     [
    { value: 'in_progress', label: 'بدء العمل',   btnClass: 'bg-green-600 text-white hover:bg-green-700' },
    { value: 'cancelled',   label: 'إلغاء',        btnClass: 'bg-red-100 text-red-700 hover:bg-red-200' },
  ],
  in_progress: [
    { value: 'on_hold',   label: 'تعليق مؤقت',    btnClass: 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' },
    { value: 'completed', label: 'إتمام العمل',    btnClass: 'bg-green-600 text-white hover:bg-green-700' },
    { value: 'cancelled', label: 'إلغاء',          btnClass: 'bg-red-100 text-red-700 hover:bg-red-200' },
  ],
  on_hold:     [
    { value: 'in_progress', label: 'استئناف العمل', btnClass: 'bg-blue-600 text-white hover:bg-blue-700' },
    { value: 'cancelled',   label: 'إلغاء',         btnClass: 'bg-red-100 text-red-700 hover:bg-red-200' },
  ],
  completed:   [{ value: 'delivered', label: 'تم التسليم', btnClass: 'bg-teal-600 text-white hover:bg-teal-700' }],
}

const allowedTransitions = computed(() =>
  STATUS_TRANSITIONS[order.value?.status] ?? []
)

async function load() {
  loading.value = true
  try {
    const { data } = await apiClient.get(`/work-orders/${id}`)
    order.value = data.data
  } finally {
    loading.value = false
  }
}

function openTransition(t: typeof transitionModal.value) {
  transitionForm.value = { technician_notes: '', mileage_out: null }
  transitionError.value = ''
  transitionModal.value = t
}

async function confirmTransition() {
  transitioning.value = true
  transitionError.value = ''
  try {
    const payload: Record<string, any> = {
      status:  transitionModal.value.value,
      version: order.value.version,
    }
    if (transitionForm.value.technician_notes) payload.technician_notes = transitionForm.value.technician_notes
    if (transitionForm.value.mileage_out) payload.mileage_out = transitionForm.value.mileage_out

    const { data } = await apiClient.patch(`/work-orders/${id}/status`, payload)
    order.value = data.data
    transitionModal.value = null
  } catch (e: any) {
    transitionError.value = e.response?.data?.message ?? 'حدث خطأ أثناء تحديث الحالة.'
  } finally {
    transitioning.value = false
  }
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    draft: 'مسودة', pending: 'قيد الانتظار', in_progress: 'جارٍ',
    on_hold: 'معلق', completed: 'مكتمل', delivered: 'تم التسليم', cancelled: 'ملغي',
  }
  return m[s] ?? s
}

function statusClass(s: string): string {
  const m: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-500', pending: 'bg-yellow-100 text-yellow-700',
    in_progress: 'bg-blue-100 text-blue-700', on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-green-100 text-green-700', delivered: 'bg-teal-100 text-teal-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[s] ?? 'bg-gray-100 text-gray-600'
}

function priorityLabel(p: string): string {
  const m: Record<string, string> = { low: 'منخفضة', normal: 'عادية', high: 'عالية', urgent: 'عاجلة' }
  return m[p] ?? p
}

function priorityClass(p: string): string {
  const m: Record<string, string> = {
    low: 'bg-gray-100 text-gray-500', normal: 'bg-blue-50 text-blue-600',
    high: 'bg-orange-100 text-orange-600', urgent: 'bg-red-100 text-red-700',
  }
  return m[p] ?? ''
}

function itemTypeLabel(t: string): string {
  const m: Record<string, string> = { part: 'قطعة', labor: 'عمالة', service: 'خدمة', other: 'أخرى' }
  return m[t] ?? t
}

function formatDate(dt: string): string {
  return new Date(dt).toLocaleString('ar-SA')
}

onMounted(load)
</script>
