<template>
  <div>
    <!-- Trigger Button -->
    <button
      class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 text-white rounded-xl text-sm font-medium hover:from-teal-700 hover:to-emerald-700 transition-all shadow-sm"
      @click="open"
    >
      <CameraIcon class="w-4 h-4" />
      مسح اللوحة بالكاميرا
    </button>

    <!-- Scanner Modal -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="visible" class="fixed inset-0 bg-black/80 z-[60] flex flex-col items-center justify-center p-4" dir="rtl">
          <!-- Header -->
          <div class="w-full max-w-md flex items-center justify-between mb-3">
            <h3 class="text-white font-semibold text-lg">مسح لوحة المركبة</h3>
            <button class="text-white/70 hover:text-white" @click="close">
              <XMarkIcon class="w-6 h-6" />
            </button>
          </div>

          <!-- Camera / Preview -->
          <div class="relative w-full max-w-md aspect-[4/3] bg-black rounded-2xl overflow-hidden border-2 border-teal-400/50">
            <!-- Live Video -->
            <video
              v-if="!captured"
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="w-full h-full object-cover"
            ></video>

            <!-- Plate Overlay Guide -->
            <div v-if="!captured" class="absolute inset-0 flex items-center justify-center pointer-events-none">
              <div class="border-2 border-teal-400 rounded-lg w-3/4 h-1/3 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]">
                <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-teal-300 text-xs whitespace-nowrap">ضع اللوحة داخل الإطار</span>
              </div>
              <!-- Corner marks -->
              <div class="absolute top-[33%] right-[12%] w-4 h-4 border-t-2 border-r-2 border-teal-400 rounded-tr"></div>
              <div class="absolute top-[33%] left-[12%] w-4 h-4 border-t-2 border-l-2 border-teal-400 rounded-tl"></div>
              <div class="absolute bottom-[33%] right-[12%] w-4 h-4 border-b-2 border-r-2 border-teal-400 rounded-br"></div>
              <div class="absolute bottom-[33%] left-[12%] w-4 h-4 border-b-2 border-l-2 border-teal-400 rounded-bl"></div>
            </div>

            <!-- Captured Image Preview -->
            <canvas v-if="captured" ref="canvasRef" class="w-full h-full object-cover"></canvas>

            <!-- Processing Overlay -->
            <div v-if="processing" class="absolute inset-0 bg-black/60 flex flex-col items-center justify-center gap-3">
              <div class="w-10 h-10 border-3 border-teal-400 border-t-transparent rounded-full animate-spin"></div>
              <p class="text-teal-300 text-sm font-medium">جاري تحليل اللوحة...</p>
            </div>
          </div>

          <!-- Extracted Plate Result -->
          <div v-if="extracted" class="mt-4 w-full max-w-md bg-white rounded-2xl p-4 space-y-3">
            <p class="text-sm text-gray-500 text-center">تم استخراج رقم اللوحة (راجع قبل الاعتماد):</p>
            <input
              v-model="editedPlate"
              class="w-full text-center font-mono text-2xl font-bold tracking-widest border-2 border-teal-500 rounded-xl py-3 focus:outline-none focus:ring-2 focus:ring-teal-400 uppercase"
              placeholder="تحقق وعدّل إذا لزم"
              @input="editedPlate = editedPlate.toUpperCase()"
            />
            <p v-if="ocrMethod" class="text-[10px] text-center text-gray-400">طريقة الاستخراج: {{ ocrMethod }}</p>
            <p v-if="ocrConfidence !== null" class="text-[10px] text-center" :class="ocrConfidence >= 0.85 ? 'text-green-600' : 'text-amber-600'">
              دقة القراءة التقريبية: {{ Math.round(ocrConfidence * 100) }}%
            </p>

            <!-- حلّ ذكي: مسجّل في النظام أم لا -->
            <div v-if="resolveInfo?.registered" class="rounded-xl bg-teal-50 border border-teal-200 p-3 text-xs text-right space-y-1">
              <p class="font-bold text-teal-800">مركبة مسجّلة</p>
              <p v-if="resolveInfo.vehicle?.customer" class="text-teal-900">
                العميل: {{ resolveInfo.vehicle.customer.name }}
              </p>
              <p v-if="resolveInfo.recent_work_orders?.length" class="text-teal-800">
                آخر أوامر عمل: {{ resolveInfo.recent_work_orders.length }} في المعاينة
              </p>
              <RouterLink
                v-if="resolveInfo.vehicle?.id"
                :to="`/vehicles/${resolveInfo.vehicle.id}`"
                class="inline-block mt-1 text-teal-700 font-medium hover:underline"
              >
                فتح ملف المركبة ←
              </RouterLink>
            </div>
            <div v-else-if="resolveInfo && editedPlate" class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900 text-right">
              غير مسجّلة في النظام — يمكنك إنشاء مركبة جديدة مع تعبئة اللوحة تلقائياً من التأكيد.
            </div>

            <p class="text-xs text-gray-400 text-center">راجع الرقم ثم اضغط تأكيد — لا يُحفَظ شيء قبل ذلك</p>
            <div class="flex gap-2">
              <button :disabled="!canConfirm" class="flex-1 bg-teal-600 disabled:opacity-50 hover:bg-teal-700 text-white rounded-xl py-2.5 text-sm font-medium transition-colors"
                      @click="confirm"
              >
                ✓ تأكيد واستخدام اللوحة
              </button>
              <button class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl py-2.5 text-sm transition-colors" @click="retake">
                ↩ إعادة المسح
              </button>
            </div>
          </div>

          <!-- Error -->
          <div v-if="error" class="mt-3 w-full max-w-md bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-600 text-center">
            {{ error }}
          </div>

          <!-- Capture Button (when no capture yet) -->
          <div v-if="!captured && !processing" class="mt-4 flex gap-3">
            <button class="w-16 h-16 rounded-full bg-white border-4 border-teal-400 hover:bg-teal-50 transition-colors flex items-center justify-center shadow-lg"
                    @click="capture"
            >
              <CameraIcon class="w-7 h-7 text-teal-600" />
            </button>
          </div>

          <!-- File Input Fallback -->
          <div class="mt-3">
            <label class="text-white/60 text-xs cursor-pointer hover:text-white/90 flex items-center gap-1.5">
              <ArrowUpTrayIcon class="w-4 h-4" />
              أو ارفع صورة من الجهاز
              <input type="file" accept="image/*" class="hidden" @change="onFileUpload" />
            </label>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Hidden canvas for processing -->
    <canvas ref="processCanvas" class="hidden"></canvas>
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted, nextTick, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { CameraIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'

const emit = defineEmits<{
  (e: 'plate', value: string): void
  (e: 'resolved', payload: { plate: string; registered: boolean; vehicleId?: number }): void
}>()

const visible     = ref(false)
const captured    = ref(false)
const processing  = ref(false)
const extracted   = ref(false)
const editedPlate = ref('')
const error       = ref('')
const ocrMethod = ref('')
const ocrConfidence = ref<number | null>(null)
const resolveInfo = ref<{
  registered: boolean
  vehicle?: { id: number; customer?: { name: string }; plate_number?: string }
  recent_work_orders?: unknown[]
} | null>(null)

const videoRef      = ref<HTMLVideoElement | null>(null)
const canvasRef     = ref<HTMLCanvasElement | null>(null)
const processCanvas = ref<HTMLCanvasElement | null>(null)
let stream: MediaStream | null = null

async function open() {
  error.value     = ''
  captured.value  = false
  extracted.value = false
  editedPlate.value = ''
  ocrMethod.value = ''
  ocrConfidence.value = null
  resolveInfo.value = null
  visible.value   = true

  // Start camera after DOM
  await new Promise(r => setTimeout(r, 150))
  await startCamera()
}

async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false,
    })
    if (videoRef.value) {
      videoRef.value.srcObject = stream
    }
  } catch {
    error.value = 'تعذّر الوصول للكاميرا — استخدم رفع الصورة بدلاً منه'
  }
}

function stopCamera() {
  stream?.getTracks().forEach(t => t.stop())
  stream = null
}

async function capture() {
  const v = videoRef.value
  const proc = processCanvas.value
  if (!v || !proc) return
  if (v.videoWidth === 0 || v.videoHeight === 0) {
    error.value = 'الكاميرا لم تجهّز بعد — انتظر لحظة ثم أعد المحاولة'
    return
  }
  proc.width = v.videoWidth
  proc.height = v.videoHeight
  proc.getContext('2d')!.drawImage(v, 0, 0)
  stopCamera()
  captured.value = true
  await nextTick()
  const preview = canvasRef.value
  if (preview) {
    preview.width = proc.width
    preview.height = proc.height
    preview.getContext('2d')!.drawImage(proc, 0, 0)
  }
  await processImage(proc)
}

async function onFileUpload(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  input.value = ''
  error.value = ''
  captured.value = true
  processing.value = true
  const url = URL.createObjectURL(file)
  const img = new Image()
  img.onload = async () => {
    try {
      const proc = processCanvas.value
      if (!proc) {
        error.value = 'تعذّر تهيئة المعاينة'
        processing.value = false
        captured.value = false
        return
      }
      proc.width = img.width
      proc.height = img.height
      proc.getContext('2d')!.drawImage(img, 0, 0)
      await nextTick()
      const preview = canvasRef.value
      if (preview) {
        preview.width = img.width
        preview.height = img.height
        preview.getContext('2d')!.drawImage(img, 0, 0)
      }
      URL.revokeObjectURL(url)
      await processImage(proc)
    } catch {
      error.value = 'تعذّر معالجة الصورة'
      processing.value = false
      extracted.value = true
    }
  }
  img.onerror = () => {
    URL.revokeObjectURL(url)
    error.value = 'تعذّر فتح ملف الصورة'
    processing.value = false
    captured.value = false
  }
  img.src = url
}

async function processImage(canvas: HTMLCanvasElement) {
  processing.value = true
  error.value = ''
  ocrMethod.value = ''
  ocrConfidence.value = null
  resolveInfo.value = null
  try {
    const base64 = canvas.toDataURL('image/jpeg', 0.85).split(',')[1]
    const { data: json } = await apiClient.post('/governance/ocr/plate', {
      image: base64,
      resolve_vehicle: true,
    })
    const pn = json.plate_normalized as { display?: string } | null | undefined
    const normalized = pn?.display ?? (json.plate as string) ?? ''
    editedPlate.value = sanitizePlateInput(String(normalized))
    ocrMethod.value =
      json.method === 'ocr'
        ? 'OCR'
        : json.method === 'unavailable'
          ? 'محرك OCR غير متاح على الخادم'
          : json.method === 'ocr_failed'
            ? 'تعذّر القراءة التلقائية — راجع الصورة أو أدخل يدوياً'
            : json.method === 'ocr_unparsed'
              ? 'OCR (لم يُطابق نمط لوحة سعودي)'
              : 'استخراج جزئي'
    if (!json.success && json.error) {
      error.value = String(json.error)
    }
    if (json.vehicle) {
      resolveInfo.value = json.vehicle as NonNullable<typeof resolveInfo.value>
    }
    ocrConfidence.value = estimatePlateConfidence(editedPlate.value, Boolean(json.success))
  } catch {
    editedPlate.value = ''
    ocrConfidence.value = null
    error.value = 'تعذّر الاتصال بالخادم — أدخل اللوحة يدوياً'
  } finally {
    processing.value = false
    extracted.value  = true
  }
}

function confirm() {
  const p = sanitizePlateInput(editedPlate.value)
  if (!p) return
  emit('plate', p)
  emit('resolved', {
    plate: p,
    registered: !!resolveInfo.value?.registered,
    vehicleId: resolveInfo.value?.vehicle?.id,
  })
  close()
}

function retake() {
  captured.value  = false
  extracted.value = false
  editedPlate.value = ''
  error.value = ''
  resolveInfo.value = null
  ocrMethod.value = ''
  ocrConfidence.value = null
  startCamera()
}

function close() {
  stopCamera()
  visible.value   = false
  captured.value  = false
  extracted.value = false
  editedPlate.value = ''
  error.value = ''
  ocrConfidence.value = null
}

const canConfirm = computed(() => {
  const value = sanitizePlateInput(editedPlate.value)
  if (!value) return false
  if (!ocrMethod.value) return true
  return estimatePlateConfidence(value, true) >= 0.55
})

function sanitizePlateInput(value: string): string {
  const upper = String(value || '').toUpperCase().trim()
  const compact = upper.replace(/[^A-Z0-9]/g, '')
  const match = compact.match(/^([A-Z]{3})(\d{4})$/)
  if (!match) return upper
  return `${match[1]} ${match[2]}`
}

function estimatePlateConfidence(value: string, success: boolean): number {
  const compact = value.replace(/[^A-Z0-9]/g, '')
  if (!success) return 0.35
  if (/^[A-Z]{3}\d{4}$/.test(compact)) return 0.96
  if (/^[A-Z]{2,3}\d{3,4}$/.test(compact)) return 0.72
  return 0.5
}

onUnmounted(stopCamera)
</script>

<style scoped>
.border-3 { border-width: 3px; }
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
