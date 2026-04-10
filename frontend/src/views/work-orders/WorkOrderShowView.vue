<template>
  <div class="space-y-6" dir="rtl" :class="{ '!space-y-3': staffUi.compactMode }">
    <NavigationSourceHint v-if="!staffUi.compactMode" />
    <div class="flex items-center justify-between">
      <div>
        <RouterLink to="/work-orders" class="text-sm text-primary-600 hover:underline">← أوامر العمل</RouterLink>
        <h2 class="text-lg font-semibold text-gray-900 mt-1">
          {{ order?.order_number ?? 'جارٍ التحميل...' }}
        </h2>
      </div>
      <div v-if="order" class="flex gap-2">
        <span :class="workOrderStatusBadgeClass(order.status)" class="px-3 py-1 rounded-full text-xs font-medium">
          {{ workOrderStatusLabel(order.status) }}
        </span>
        <span v-if="!staffUi.compactMode" :class="priorityClass(order.priority)" class="px-3 py-1 rounded-full text-xs">
          {{ priorityLabel(order.priority) }}
        </span>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-gray-400">جارٍ التحميل...</div>

    <div v-else-if="loadError" class="rounded-xl border border-red-100 bg-red-50 px-4 py-8 text-center text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-200">
      {{ loadError }}
    </div>

    <template v-else-if="order">
      <!-- مشاركة و PDF — مهمّة في الوضع المضغوط أيضاً (الفني/الورشة) -->
      <div
        class="bg-white rounded-xl border border-gray-200 space-y-3"
        :class="staffUi.compactMode ? 'p-3 space-y-2' : 'p-4'"
      >
        <h3 class="font-semibold text-gray-800" :class="staffUi.compactMode ? 'text-xs' : 'text-sm'">
          مشاركة أمر العمل
        </h3>
        <p v-if="!staffUi.compactMode" class="text-xs text-gray-500 leading-relaxed">
          تنزيل ملف PDF يتضمّن رمز QR للتحقق من الأمر، وبيانات الجهة المصدّرة (الشركة/الفرع). يمكن إرسال النسخة بالبريد أو فتح واتساب يدوياً، أو إرسال رسالة نصية لرقم السائق عند ضبط Twilio/واجهة واتساب في الإعدادات.
        </p>
        <p v-else class="text-[11px] text-gray-500 leading-snug">
          نسخ رابط التحقق أو النص، أو فتح واتساب — نفس القدرات متوفرة في الوضع المضغوط.
        </p>
        <div class="flex flex-wrap gap-2" :class="staffUi.compactMode ? 'gap-1.5' : ''">
          <button
            type="button"
            class="rounded-lg border border-violet-200 bg-violet-50 text-violet-900 hover:bg-violet-100 disabled:opacity-50 font-medium"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            :disabled="shareActionBusy"
            title="نسخ رابط الصفحة العامة للتحقق من الأمر"
            @click="copyPublicVerifyLink"
          >
            {{ shareActionBusy ? '…' : 'نسخ رابط التحقق' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-violet-200 text-violet-800 hover:bg-violet-50 disabled:opacity-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            :disabled="shareActionBusy"
            @click="copyShareMessageText"
          >
            نسخ نص المشاركة
          </button>
          <button
            v-if="canWebShare"
            type="button"
            class="rounded-lg border border-sky-200 bg-sky-50 text-sky-900 hover:bg-sky-100 disabled:opacity-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            :disabled="shareActionBusy"
            @click="systemShareWorkOrder"
          >
            مشاركة النظام…
          </button>
          <button
            type="button"
            class="rounded-lg bg-slate-800 text-white hover:bg-slate-900 disabled:opacity-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            :disabled="pdfDownloading"
            @click="downloadWorkOrderPdf"
          >
            {{ pdfDownloading ? 'جارٍ التنزيل…' : 'تنزيل PDF' }}
          </button>
          <button
            type="button"
            class="rounded-lg border border-gray-300 hover:bg-gray-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            @click="openWhatsAppShare"
          >
            واتساب (رسالة جاهزة)
          </button>
          <button
            type="button"
            class="rounded-lg border border-emerald-200 text-emerald-800 hover:bg-emerald-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            @click="openWhatsAppDriverTab"
          >
            واتساب السائق
          </button>
          <button
            type="button"
            class="rounded-lg border border-gray-300 hover:bg-gray-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            :disabled="emailSending"
            @click="shareWorkOrderByEmail"
          >
            {{ emailSending ? 'جارٍ الإرسال…' : 'بريد + PDF' }}
          </button>
          <button
            type="button"
            class="rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50"
            :class="staffUi.compactMode ? 'px-2 py-1.5 text-[11px]' : 'px-3 py-2 text-sm'"
            :disabled="waDriverSending"
            :title="'يتطلب ضبط واتساب في إعدادات الشركة (Twilio أو واجهة مخصّصة)'"
            @click="sendDriverWhatsAppViaProvider"
          >
            {{ waDriverSending ? 'جارٍ الإرسال…' : 'إرسال لواتساب السائق (الخادم)' }}
          </button>
        </div>
      </div>

      <!-- تفاصيل -->
      <div class="grid gap-4" :class="staffUi.compactMode ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-2'">
        <div class="bg-white rounded-xl border border-gray-200 space-y-3" :class="staffUi.compactMode ? 'p-3' : 'p-5'">
          <h3 class="font-medium text-gray-800" :class="{ '!text-sm': staffUi.compactMode }">بيانات العميل والمركبة</h3>
          <dl class="space-y-2 text-sm" :class="{ '!text-xs': staffUi.compactMode }">
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
            <div v-if="staffUi.compactMode" class="flex justify-between">
              <dt class="text-gray-500">الإجمالي التقديري</dt>
              <dd class="font-semibold">{{ Number(order.estimated_total).toFixed(2) }} ر.س</dd>
            </div>
            <div v-if="staffUi.compactMode" class="pt-2 border-t border-gray-100 text-xs text-gray-500 flex flex-wrap gap-x-3 gap-y-1">
              <span>{{ order.branch?.name ?? '—' }}</span>
              <span>·</span>
              <span>فني: {{ order.assigned_technician?.name ?? '—' }}</span>
              <span>·</span>
              <span>إنشاء: {{ formatDate(order.created_at) }}</span>
            </div>
          </dl>
        </div>

        <div v-if="!staffUi.compactMode" class="bg-white rounded-xl border border-gray-200 p-5 space-y-3">
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

      <!-- وصف الطلب / الملاحظات -->
      <div v-if="order.customer_complaint || order.technician_notes || order.diagnosis"
           class="bg-white rounded-xl border border-gray-200 space-y-3"
           :class="staffUi.compactMode ? 'p-3' : 'p-5'"
      >
        <div v-if="order.customer_complaint">
          <h4 class="text-xs font-semibold text-gray-500 uppercase mb-1">وصف الطلب</h4>
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
        <div class="border-b border-gray-100" :class="staffUi.compactMode ? 'px-3 py-2' : 'px-5 py-3'">
          <h3 class="font-medium text-gray-800" :class="{ '!text-sm': staffUi.compactMode }">البنود والخدمات</h3>
        </div>
        <table class="w-full" :class="staffUi.compactMode ? 'text-xs' : 'text-sm'">
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

      <div
        v-if="order.status === 'cancellation_requested'"
        class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
        :class="staffUi.compactMode ? 'p-3' : 'p-4'"
      >
        يوجد طلب إلغاء قيد المراجعة الإدارية — لا يمكن تعديل الأمر حتى يُبتّ في الطلب.
      </div>

      <!-- تحديث الحالة -->
      <div
        v-if="allowedTransitions.length && order.status !== 'cancellation_requested'"
        class="bg-white rounded-xl border border-gray-200"
        :class="staffUi.compactMode ? 'p-3' : 'p-5'"
      >
        <h3 class="font-medium text-gray-800 mb-4" :class="{ '!text-sm !mb-2': staffUi.compactMode }">تحديث الحالة</h3>
        <div class="flex gap-3 flex-wrap">
          <button
            v-for="t in allowedTransitions"
            :key="t.value"
            type="button"
            :disabled="Boolean(t.disabled)"
            :title="t.disabledHint"
            :class="[t.btnClass, t.disabled ? 'opacity-45 cursor-not-allowed' : '']"
            class="px-4 py-2 text-sm rounded-lg font-medium transition-colors"
            @click="openTransition(t)"
          >
            {{ t.label }}
          </button>
        </div>
      </div>

      <div
        v-if="['approved', 'in_progress', 'on_hold'].includes(order.status)"
        class="bg-white rounded-xl border border-gray-200 space-y-3"
        :class="staffUi.compactMode ? 'p-3' : 'p-5'"
      >
        <h3 class="font-medium text-gray-800" :class="{ '!text-sm': staffUi.compactMode }">طلب إلغاء رسمي</h3>
        <p class="text-xs text-gray-500">للأوامر المعتمدة أو قيد التنفيذ يتم الإلغاء عبر طلب مراجعة إدارية فقط.</p>
        <textarea
          v-model="cancellationReason"
          rows="3"
          class="w-full border border-gray-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-900"
          placeholder="السبب (إلزامي)..."
        />
        <button
          type="button"
          class="px-4 py-2 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50"
          :disabled="cancellationSubmitting || order.status === 'cancellation_requested'"
          @click="startCancellationPreview"
        >
          {{ cancellationSubmitting ? 'جارٍ المعاينة...' : 'معاينة وطلب الإلغاء' }}
        </button>
        <p v-if="cancellationError" class="text-sm text-red-600">{{ cancellationError }}</p>
      </div>
      <!-- توقيع السائق -->
      <template v-if="order">
        <div class="bg-white rounded-xl border border-gray-200 space-y-3" :class="staffUi.compactMode ? 'p-3' : 'p-5'">
          <h3 class="font-medium text-gray-800" :class="{ '!text-sm': staffUi.compactMode }">توقيع السائق</h3>
          <canvas
            ref="signatureCanvas"
            width="600"
            :height="staffUi.compactMode ? 110 : 160"
            class="w-full border border-gray-300 rounded-lg bg-gray-50 cursor-crosshair touch-none"
            :class="{ 'max-h-[100px]': staffUi.compactMode }"
            @mousedown="startDraw"
            @mousemove="draw"
            @mouseup="stopDraw"
            @mouseleave="stopDraw"
            @touchstart.prevent="startDrawTouch"
            @touchmove.prevent="drawTouch"
            @touchend="stopDraw"
          ></canvas>
          <div class="flex gap-2">
            <button class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors" @click="clearSignature">
              مسح
            </button>
            <button class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors" @click="saveSignature">
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

      <SensitiveOperationReviewModal
        v-model="sensitiveOpen"
        :summary="sensitiveSummary"
        :loading="sensitiveLoading"
        :error="sensitiveError"
        :confirm-text="sensitiveConfirmLabel"
        :confirm-disabled="sensitiveConfirmDisabled"
        :title="sensitiveTitle"
        @confirm="confirmSensitiveAction"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'
import NavigationSourceHint from '@/components/NavigationSourceHint.vue'
import SensitiveOperationReviewModal from '@/components/SensitiveOperationReviewModal.vue'
import { useStaffUiStore } from '@/stores/staffUi'
import { useToast } from '@/composables/useToast'
import { workOrderStatusLabel, workOrderStatusBadgeClass } from '@/utils/workOrderStatusLabels'

const route = useRoute()
const staffUi = useStaffUiStore()
const toast = useToast()
const id    = Number(route.params.id)

const pdfDownloading = ref(false)
const emailSending = ref(false)
const waDriverSending = ref(false)
const shareActionBusy = ref(false)

/** مشاركة عبر نافذة النظام (موبايل / بعض المتصفحات) */
const canWebShare = computed(
  () => typeof navigator !== 'undefined' && typeof navigator.share === 'function',
)

const order       = ref<any>(null)
const loading     = ref(false)
const loadError   = ref('')
const transitionModal = ref<any>(null)
const transitioning   = ref(false)
const transitionError = ref('')
const transitionForm  = ref({ technician_notes: '', mileage_out: null as number | null })

const sensitiveOpen = ref(false)
const sensitiveSummary = ref<Record<string, any> | null>(null)
const sensitiveToken = ref('')
const sensitiveLoading = ref(false)
const sensitiveError = ref('')
const sensitiveMode = ref<'approved' | 'cancellation' | null>(null)
const sensitiveTitle = ref('مراجعة نهائية')
const sensitiveConfirmLabel = ref('تأكيد')
const cancellationReason = ref('')
const cancellationSubmitting = ref(false)
const cancellationError = ref('')

const sensitiveConfirmDisabled = computed(() => {
  if (sensitiveMode.value === 'cancellation') {
    return cancellationReason.value.trim().length < 3
  }
  return false
})

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
  draft: [
    { value: 'pending_manager_approval', label: 'إرسال للمراجعة', btnClass: 'bg-blue-600 text-white hover:bg-blue-700' },
    { value: 'cancelled', label: 'إلغاء', btnClass: 'bg-red-100 text-red-700 hover:bg-red-200' },
  ],
  pending_manager_approval: [
    { value: 'approved', label: 'اعتماد أمر العمل', btnClass: 'bg-emerald-600 text-white hover:bg-emerald-700' },
    { value: 'cancelled', label: 'إلغاء', btnClass: 'bg-red-100 text-red-700 hover:bg-red-200' },
  ],
  approved: [
    { value: 'in_progress', label: 'بدء التنفيذ', btnClass: 'bg-green-600 text-white hover:bg-green-700' },
  ],
  in_progress: [
    { value: 'on_hold', label: 'تعليق مؤقت', btnClass: 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200' },
    { value: 'completed', label: 'إتمام العمل', btnClass: 'bg-green-600 text-white hover:bg-green-700' },
  ],
  on_hold: [
    { value: 'in_progress', label: 'استئناف التنفيذ', btnClass: 'bg-blue-600 text-white hover:bg-blue-700' },
  ],
  completed: [{ value: 'delivered', label: 'تم التسليم', btnClass: 'bg-teal-600 text-white hover:bg-teal-700' }],
}

const allowedTransitions = computed(() => {
  const raw = STATUS_TRANSITIONS[order.value?.status] ?? []
  const hasLines = (order.value?.items?.length ?? 0) > 0
  return raw.map((t) => ({
    ...t,
    disabled: t.value === 'approved' && !hasLines,
    disabledHint: t.value === 'approved' && !hasLines ? 'أضف بند خدمة أو منتج قبل اعتماد أمر العمل' : undefined,
  }))
})

async function downloadWorkOrderPdf() {
  pdfDownloading.value = true
  try {
    const res = await apiClient.get(`/work-orders/${id}/pdf`, {
      responseType: 'blob',
      skipGlobalErrorToast: true,
      headers: { Accept: 'application/pdf' },
    })
    const blob = res.data as Blob
    const contentType = String(res.headers?.['content-type'] ?? '').toLowerCase()
    if (!contentType.includes('application/pdf')) {
      const serverMessage = await extractBlobMessage(blob)
      throw new Error(serverMessage || 'الاستجابة ليست ملف PDF صالحاً.')
    }
    if (blob.size < 128) {
      const serverMessage = await extractBlobMessage(blob)
      throw new Error(serverMessage || 'الملف الناتج فارغ أو غير مكتمل.')
    }
    const href = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = href
    a.download = `${order.value?.order_number ?? 'work-order'}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(href)
    toast.success('تم التنزيل', 'تم حفظ ملف PDF.')
  } catch (e: unknown) {
    const msg = e instanceof Error && e.message ? e.message : summarizeAxiosError(e)
    toast.error('تعذّر التنزيل', msg)
  } finally {
    pdfDownloading.value = false
  }
}

async function extractBlobMessage(blob: Blob): Promise<string> {
  try {
    const txt = await blob.text()
    if (!txt) return ''
    try {
      const j = JSON.parse(txt) as { message?: string; error?: string }
      return String(j.message ?? j.error ?? '').trim()
    } catch {
      return txt.slice(0, 240).trim()
    }
  } catch {
    return ''
  }
}

async function fetchShareLinks() {
  const { data } = await apiClient.get(`/work-orders/${id}/share-links`, { skipGlobalErrorToast: true })
  return data.data as {
    public_verify_url: string
    whatsapp_open_href: string
    whatsapp_driver_href: string | null
    share_text: string
    driver_phone: string | null
  }
}

async function copyToClipboard(text: string): Promise<boolean> {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
      return true
    }
  } catch {
    /* fallback */
  }
  try {
    const ta = document.createElement('textarea')
    ta.value = text
    ta.setAttribute('readonly', '')
    ta.style.position = 'fixed'
    ta.style.left = '-9999px'
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(ta)
    return ok
  } catch {
    return false
  }
}

async function copyPublicVerifyLink() {
  shareActionBusy.value = true
  try {
    const links = await fetchShareLinks()
    const ok = await copyToClipboard(links.public_verify_url)
    if (ok) toast.success('تم النسخ', 'رابط صفحة التحقق العامة جاهز للصق وللمشاركة المباشرة.')
    else toast.error('تعذّر النسخ', 'اسمح للمتصفح بالوصول إلى الحافظة أو انسخ الرابط يدوياً من النافذة التالية.')
  } catch (e: unknown) {
    toast.error('تعذّر جلب الرابط', summarizeAxiosError(e))
  } finally {
    shareActionBusy.value = false
  }
}

async function copyShareMessageText() {
  shareActionBusy.value = true
  try {
    const links = await fetchShareLinks()
    const ok = await copyToClipboard(links.share_text)
    if (ok) toast.success('تم النسخ', 'النص جاهز للصق في واتساب أو أي تطبيق.')
    else toast.error('تعذّر النسخ', 'جرّب السماح بالوصول إلى الحافظة.')
  } catch (e: unknown) {
    toast.error('تعذّر جلب النص', summarizeAxiosError(e))
  } finally {
    shareActionBusy.value = false
  }
}

async function systemShareWorkOrder() {
  shareActionBusy.value = true
  try {
    const links = await fetchShareLinks()
    if (typeof navigator.share !== 'function') {
      await copyShareMessageText()
      return
    }
    await navigator.share({
      title: `أمر عمل ${order.value?.order_number ?? ''}`.trim(),
      text: links.share_text,
      url: links.public_verify_url,
    })
  } catch (e: unknown) {
    const err = e as { name?: string }
    if (err?.name === 'AbortError') return
    toast.error('تعذّرت المشاركة', summarizeAxiosError(e))
  } finally {
    shareActionBusy.value = false
  }
}

async function openWhatsAppShare() {
  try {
    const links = await fetchShareLinks()
    window.open(links.whatsapp_open_href, '_blank', 'noopener,noreferrer')
  } catch (e: unknown) {
    toast.error('تعذّر فتح واتساب', summarizeAxiosError(e))
  }
}

async function openWhatsAppDriverTab() {
  try {
    const links = await fetchShareLinks()
    if (!links.whatsapp_driver_href) {
      toast.warning('لا يوجد رقم سائق', 'أضف حقل رقم السائق في أمر العمل ثم أعد المحاولة.')
      return
    }
    window.open(links.whatsapp_driver_href, '_blank', 'noopener,noreferrer')
  } catch (e: unknown) {
    toast.error('تعذّر فتح واتساب', summarizeAxiosError(e))
  }
}

async function shareWorkOrderByEmail() {
  const email = window.prompt('أدخل البريد الإلكتروني للمستلم:')
  if (!email || !email.trim()) return
  emailSending.value = true
  try {
    await apiClient.post(`/work-orders/${id}/share-email`, { email: email.trim() })
    toast.success('تم الإرسال', 'أُرسل البريد مع مرفق PDF.')
  } catch (e: unknown) {
    toast.error('تعذّر الإرسال', summarizeAxiosError(e))
  } finally {
    emailSending.value = false
  }
}

async function sendDriverWhatsAppViaProvider() {
  waDriverSending.value = true
  try {
    await apiClient.post(`/work-orders/${id}/share-whatsapp-driver`, {})
    toast.success('تم الإرسال', 'أُرسلت الرسالة عبر مزوّد واتساب المضبوط للشركة.')
  } catch (e: unknown) {
    toast.error('تعذّر الإرسال', summarizeAxiosError(e))
  } finally {
    waDriverSending.value = false
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await apiClient.get(`/work-orders/${id}`, { skipGlobalErrorToast: true })
    order.value = data.data
  } catch (e: unknown) {
    order.value = null
    loadError.value = summarizeAxiosError(e)
  } finally {
    loading.value = false
  }
}

async function openTransition(t: typeof transitionModal.value) {
  transitionError.value = ''
  if (t.disabled) return
  if (t.value === 'approved') {
    sensitiveMode.value = 'approved'
    sensitiveTitle.value = 'مراجعة اعتماد أمر العمل'
    sensitiveConfirmLabel.value = 'اعتماد بعد المراجعة'
    sensitiveOpen.value = true
    sensitiveLoading.value = true
    sensitiveError.value = ''
    sensitiveSummary.value = null
    sensitiveToken.value = ''
    try {
      const { data } = await apiClient.post('/sensitive-operations/preview', {
        operation: 'work_order_status_to_approved',
        work_order_ids: [id],
      })
      sensitiveSummary.value = data.data
      sensitiveToken.value = data.data.sensitive_preview_token
    } catch (e: unknown) {
      sensitiveError.value = summarizeAxiosError(e)
    } finally {
      sensitiveLoading.value = false
    }
    return
  }
  transitionForm.value = { technician_notes: '', mileage_out: null }
  transitionModal.value = t
}

async function startCancellationPreview() {
  cancellationError.value = ''
  const reason = cancellationReason.value.trim()
  if (reason.length < 3) {
    cancellationError.value = 'أدخل سبباً واضحاً (3 أحرف على الأقل).'
    return
  }
  sensitiveMode.value = 'cancellation'
  sensitiveTitle.value = 'مراجعة طلب إلغاء أمر العمل'
  sensitiveConfirmLabel.value = 'إرسال طلب الإلغاء'
  sensitiveOpen.value = true
  sensitiveLoading.value = true
  sensitiveError.value = ''
  sensitiveSummary.value = null
  sensitiveToken.value = ''
  cancellationSubmitting.value = true
  try {
    const { data } = await apiClient.post('/sensitive-operations/preview', {
      operation: 'work_order_cancellation_request',
      work_order_ids: [id],
    })
    sensitiveSummary.value = data.data
    sensitiveToken.value = data.data.sensitive_preview_token
  } catch (e: unknown) {
    sensitiveError.value = summarizeAxiosError(e)
  } finally {
    sensitiveLoading.value = false
    cancellationSubmitting.value = false
  }
}

async function confirmSensitiveAction() {
  if (!order.value || !sensitiveToken.value) return
  sensitiveError.value = ''
  transitioning.value = true
  try {
    if (sensitiveMode.value === 'approved') {
      const { data } = await apiClient.patch(`/work-orders/${id}/status`, {
        status: 'approved',
        version: order.value.version,
        sensitive_preview_token: sensitiveToken.value,
      })
      order.value = data.data
    } else if (sensitiveMode.value === 'cancellation') {
      const { data } = await apiClient.post(`/work-orders/${id}/cancellation-requests`, {
        reason: cancellationReason.value.trim(),
        sensitive_preview_token: sensitiveToken.value,
      })
      await load()
      if (data?.data) {
        /* refresh shows cancellation_requested */
      }
      cancellationReason.value = ''
    }
    sensitiveOpen.value = false
    sensitiveMode.value = null
  } catch (e: unknown) {
    sensitiveError.value = summarizeAxiosError(e)
  } finally {
    transitioning.value = false
  }
}

async function confirmTransition() {
  if (transitioning.value) return
  transitioning.value = true
  transitionError.value = ''
  try {
    const payload: Record<string, any> = {
      status:  transitionModal.value.value,
      version: order.value.version,
    }
    if (transitionForm.value.technician_notes) payload.technician_notes = transitionForm.value.technician_notes
    if (transitionForm.value.mileage_out) payload.mileage_out = transitionForm.value.mileage_out

    const { data } = await apiClient.patch(`/work-orders/${id}/status`, payload, { skipGlobalErrorToast: true })
    order.value = data.data
    transitionModal.value = null
  } catch (e: unknown) {
    transitionError.value = summarizeAxiosError(e)
  } finally {
    transitioning.value = false
  }
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
