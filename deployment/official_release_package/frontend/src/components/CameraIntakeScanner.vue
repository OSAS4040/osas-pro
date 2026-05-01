<template>
  <div>
    <button
      type="button"
      class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 via-emerald-600 to-violet-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition-all hover:from-teal-700 hover:via-emerald-700 hover:to-violet-700"
      @click="open"
    >
      <CameraIcon class="h-4 w-4 shrink-0" />
      {{ l('كاميرا: باركود أو QR (أمر عمل / مركبة) أو صورة لوحة', 'Camera: barcode/QR (WO or vehicle) or plate photo') }}
    </button>

    <Teleport to="body">
      <Transition name="modal-fade">
        <div
          v-if="visible"
          class="fixed inset-0 z-[60] flex flex-col items-center justify-center bg-black/80 p-4"
          dir="rtl"
        >
          <div class="mb-3 flex w-full max-w-md items-center justify-between">
            <h3 class="text-lg font-semibold text-white">
              {{ l('مسح باركود أو QR — أو التقاط اللوحة', 'Scan barcode/QR — or snap plate') }}
            </h3>
            <button type="button" class="text-white/70 hover:text-white" @click="close">
              <XMarkIcon class="h-6 w-6" />
            </button>
          </div>

          <div class="relative aspect-[4/3] w-full max-w-md overflow-hidden rounded-2xl border-2 border-teal-400/50 bg-black">
            <video
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="h-full w-full object-cover"
            />
            <div
              v-if="scanningBarcode"
              class="pointer-events-none absolute inset-0 flex items-center justify-center"
            >
              <div class="h-2/5 w-4/5 rounded-lg border-2 border-teal-300 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
            </div>
            <div v-if="processingOcr" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-black/60">
              <div class="h-10 w-10 animate-spin rounded-full border-3 border-teal-400 border-t-transparent" />
              <p class="text-sm font-medium text-teal-200">{{ l('جاري تحليل اللوحة…', 'Reading plate…') }}</p>
            </div>
            <div class="absolute inset-x-0 bottom-2 flex justify-center px-2">
              <span class="rounded-full bg-black/55 px-3 py-1 text-center text-[11px] leading-snug text-teal-50">
                {{
                  l(
                    'يوجّه نحو باركود أمر العمل أو مركبة أو QR — أو زر التقاط للوحة',
                    'Point at WO/vehicle barcode or QR — or shutter for plate',
                  )
                }}
              </span>
            </div>
          </div>

          <p v-if="hint" class="mt-3 max-w-md text-center text-xs text-amber-200">{{ hint }}</p>
          <p v-if="error" class="mt-2 max-w-md text-center text-sm text-red-200">{{ error }}</p>

          <div class="mt-4 flex w-full max-w-md flex-wrap justify-center gap-2">
            <button
              v-if="!scanningBarcode && detector && stream"
              type="button"
              class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700"
              @click="startBarcodeLoop"
            >
              {{ l('استئناف مسح الباركود', 'Resume barcode scan') }}
            </button>
            <button
              v-if="scanningBarcode"
              type="button"
              class="rounded-xl bg-white/10 px-4 py-2 text-sm text-white hover:bg-white/20"
              @click="stopBarcodeLoop"
            >
              {{ l('إيقاف مسح الباركود', 'Stop barcode scan') }}
            </button>
            <button
              v-if="stream && !processingOcr"
              type="button"
              class="flex h-14 w-14 items-center justify-center rounded-full border-4 border-teal-400 bg-white shadow-lg transition-colors hover:bg-teal-50"
              :title="l('التقاط صورة للوحة', 'Snap plate photo')"
              @click="capturePlateOcr"
            >
              <CameraIcon class="h-7 w-7 text-teal-600" />
            </button>
            <label
              class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-white/25 px-4 py-2 text-xs text-white/80 hover:bg-white/10"
            >
              <ArrowUpTrayIcon class="h-4 w-4" />
              {{ l('صورة من المعرض', 'Gallery') }}
              <input type="file" accept="image/*" class="hidden" @change="onGalleryFile" />
            </label>
          </div>
        </div>
      </Transition>
    </Teleport>

    <canvas ref="processCanvasRef" class="hidden" />
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted, nextTick } from 'vue'
import { CameraIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'
import { useLocale } from '@/composables/useLocale'
import apiClient from '@/lib/apiClient'

const emit = defineEmits<{
  (e: 'plate', value: string): void
  (e: 'order', value: string): void
  /** نفس هيكل GET intake-lookup — بعد POST intake-lookup-camera لتجنّب طلب إضافي */
  (e: 'intake', payload: Record<string, unknown>): void
}>()

const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const visible = ref(false)
const scanningBarcode = ref(false)
const processingOcr = ref(false)
const error = ref('')
const hint = ref('')

const videoRef = ref<HTMLVideoElement | null>(null)
const processCanvasRef = ref<HTMLCanvasElement | null>(null)

let stream: MediaStream | null = null
let detector: { detect: (source: ImageBitmapSource) => Promise<Array<{ rawValue?: string }>> } | null = null
let rafId: number | null = null
let lastDetectMs = 0

function normalizeBarcode(raw: string): string {
  let s = String(raw ?? '').trim()
  if (s.startsWith('*')) s = s.slice(1).trim()
  return s
}

function sanitizePlateFromApi(value: string): string {
  const upper = String(value || '').toUpperCase().trim()
  const compact = upper.replace(/[^A-Z0-9]/g, '')
  const match = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (!match) return upper.replace(/\s+/g, ' ').trim()
  return `${match[1]} ${match[2]}`
}

function classifyBarcodePayload(raw: string): { kind: 'plate' | 'order'; value: string } {
  const t = normalizeBarcode(raw)
  const compact = t.toUpperCase().replace(/[^A-Z0-9]/g, '')
  const m = compact.match(/^([A-Z]{3})(\d{3,4})$/)
  if (m) {
    return { kind: 'plate', value: `${m[1]} ${m[2]}` }
  }
  return { kind: 'order', value: t }
}

async function ensureDetector(): Promise<boolean> {
  if (typeof window === 'undefined' || !('BarcodeDetector' in window)) {
    return false
  }
  try {
    type BDClass = {
      new (opts?: { formats?: string[] }): { detect: (source: ImageBitmapSource) => Promise<Array<{ rawValue?: string }>> }
      getSupportedFormats?: () => Promise<string[]>
    }
    const BD = (window as unknown as { BarcodeDetector: BDClass }).BarcodeDetector
    const supported = typeof BD.getSupportedFormats === 'function' ? await BD.getSupportedFormats() : []
    const preferred = ['code_128', 'code_39', 'codabar', 'ean_13', 'itf', 'qr_code', 'data_matrix']
    const formats = preferred.filter((f) => supported.length === 0 || supported.includes(f))
    detector = new BD({ formats: formats.length ? formats : ['qr_code', 'code_128'] })
    return true
  } catch {
    detector = null
    return false
  }
}

function stopStream() {
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
  if (videoRef.value) {
    videoRef.value.srcObject = null
  }
}

function stopBarcodeLoop() {
  scanningBarcode.value = false
  if (rafId !== null) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
}

async function startCamera(): Promise<boolean> {
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false,
    })
    const el = videoRef.value
    if (el) {
      el.srcObject = stream
      el.setAttribute('playsinline', 'true')
      const tryPlay = () => void el.play().catch(() => {})
      tryPlay()
      el.addEventListener('loadedmetadata', tryPlay, { once: true })
    }
    return true
  } catch {
    error.value = l('تعذّر فتح الكاميرا.', 'Could not open camera.')
    return false
  }
}

/** يستخرج هيكل «data» من استجابة Laravel ({ data, trace_id }) أو يمرّر كما هو */
function unwrapIntakeAxiosBody(body: unknown): Record<string, unknown> | null {
  if (!body || typeof body !== 'object') return null
  const o = body as Record<string, unknown>
  const inner = o.data
  if (inner && typeof inner === 'object') return inner as Record<string, unknown>
  return o
}

function plateReadableFromIntake(inner: Record<string, unknown> | null): string | null {
  if (!inner) return null
  const cam = inner.camera_lookup as Record<string, unknown> | undefined
  if (cam?.success === true) {
    const cands = cam.candidates as Array<{ plate?: string }> | undefined
    const p = cands?.[0]?.plate
    if (typeof p === 'string' && p.trim()) return p.trim()
  }
  const lk = inner.lookup as Record<string, unknown> | undefined
  const lp = lk?.plate_number
  if (typeof lp === 'string' && lp.trim()) return lp.trim()
  const veh = inner.vehicle as Record<string, unknown> | undefined
  const vpn = veh?.plate_number
  if (typeof vpn === 'string' && vpn.trim()) return vpn.trim()
  return null
}

function emitBarcodeResult(raw: string) {
  const { kind, value } = classifyBarcodePayload(raw)
  if (kind === 'plate') {
    emit('plate', value)
  } else {
    emit('order', value)
  }
  close()
}

function startBarcodeLoop() {
  if (!detector || !videoRef.value) return
  stopBarcodeLoop()
  scanningBarcode.value = true
  lastDetectMs = 0
  const tick = async (ts: number) => {
    if (!scanningBarcode.value || !detector || !videoRef.value) return
    if (processingOcr.value) {
      rafId = window.requestAnimationFrame((t) => void tick(t))
      return
    }
    if (ts - lastDetectMs < 220) {
      rafId = window.requestAnimationFrame((t) => void tick(t))
      return
    }
    lastDetectMs = ts
    const v = videoRef.value
    if (v.readyState >= 2 && v.videoWidth > 0) {
      try {
        const results = await detector.detect(v)
        if (results?.length) {
          const raw = results[0]?.rawValue
          if (raw) {
            const code = normalizeBarcode(raw)
            if (code) {
              emitBarcodeResult(code)
              return
            }
          }
        }
      } catch {
        /* ignore */
      }
    }
    if (scanningBarcode.value) {
      rafId = window.requestAnimationFrame((t) => void tick(t))
    }
  }
  rafId = window.requestAnimationFrame((t) => void tick(t))
}

async function processPlateImage(canvas: HTMLCanvasElement) {
  processingOcr.value = true
  error.value = ''
  try {
    const base64 = canvas.toDataURL('image/jpeg', 0.85).split(',')[1] ?? ''
    /* مسار أوامر العمل — صلاحية work_orders.view فقط (لا users.update كمسار الحوكمة) */
    const { data: laravelBody } = await apiClient.post('/work-orders/intake-lookup-camera', {
      image: base64,
    })
    const inner = unwrapIntakeAxiosBody(laravelBody)
    const plateRaw = plateReadableFromIntake(inner)
    const plate = plateRaw ? sanitizePlateFromApi(plateRaw) : ''
    const cam = inner?.camera_lookup as Record<string, unknown> | undefined
    const ocrUsed = cam?.used === true
    const ocrOk = cam?.success === true

    if (inner && (plate || inner.work_order || inner.vehicle || (ocrUsed && ocrOk))) {
      emit('intake', inner)
      close()
      return
    }

    if (ocrUsed && !ocrOk) {
      error.value = l(
        'لم تُستخرج لوحة واضحة من الصورة — أعد المحاولة أو أدخل اللوحة يدوياً.',
        'Could not read a plate from the photo — retry or enter the plate manually.',
      )
      return
    }
    error.value = l('لم تُستخرج لوحة واضحة من الصورة.', 'Could not read plate from image.')
  } catch {
    error.value = l('تعذّر تحليل صورة اللوحة.', 'Plate OCR failed.')
  } finally {
    processingOcr.value = false
    if (visible.value && detector && stream && !scanningBarcode.value) {
      startBarcodeLoop()
    }
  }
}

async function capturePlateOcr() {
  const v = videoRef.value
  const proc = processCanvasRef.value
  if (!v || !proc || v.videoWidth === 0) {
    error.value = l('الكاميرا غير جاهزة.', 'Camera not ready.')
    return
  }
  stopBarcodeLoop()
  proc.width = v.videoWidth
  proc.height = v.videoHeight
  proc.getContext('2d')!.drawImage(v, 0, 0)
  await processPlateImage(proc)
}

async function onGalleryFile(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  error.value = ''
  const bmp = await createImageBitmap(file)
  try {
    if (detector) {
      try {
        const results = await detector.detect(bmp)
        if (results?.length) {
          const raw = normalizeBarcode(results[0]?.rawValue ?? '')
          if (raw) {
            bmp.close()
            emitBarcodeResult(raw)
            return
          }
        }
      } catch {
        /* OCR path */
      }
    }
    const proc = processCanvasRef.value
    if (!proc) {
      bmp.close()
      return
    }
    proc.width = bmp.width
    proc.height = bmp.height
    proc.getContext('2d')!.drawImage(bmp, 0, 0)
    bmp.close()
    await processPlateImage(proc)
  } catch {
    bmp.close()
    error.value = l('تعذّر فتح الصورة.', 'Could not open image.')
  }
}

async function open() {
  visible.value = true
  error.value = ''
  hint.value = ''
  processingOcr.value = false
  stopBarcodeLoop()
  stopStream()
  await nextTick()
  await new Promise((r) => setTimeout(r, 120))
  const detOk = await ensureDetector()
  if (!detOk) {
    hint.value = l(
      'مسح الباركود التلقائي يحتاج Chrome أو Edge — يمكنك التقاط صورة للوحة أو اختيار صورة من المعرض.',
      'Live barcode needs Chrome/Edge — use shutter or gallery for plate.',
    )
  }
  const ocrTip = l(
    'لقراءة اللوحة: إضاءة جيدة، صورة ثابتة، وتفضيل HTTPS. إن فشل التعرف أعد المحاولة أو أدخل اللوحة يدوياً.',
    'For plate OCR: good light, steady shot, HTTPS preferred. If recognition fails, retry or enter the plate manually.',
  )
  hint.value = hint.value ? `${hint.value} ${ocrTip}` : ocrTip
  const camOk = await startCamera()
  if (camOk && detOk) {
    startBarcodeLoop()
  }
}

function close() {
  stopBarcodeLoop()
  stopStream()
  visible.value = false
  processingOcr.value = false
  error.value = ''
}

onUnmounted(() => {
  close()
})
</script>

<style scoped>
.border-3 {
  border-width: 3px;
}
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
