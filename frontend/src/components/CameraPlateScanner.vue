<template>
  <div>
    <!-- Trigger Button -->
    <button
      @click="open"
      class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 text-white rounded-xl text-sm font-medium hover:from-teal-700 hover:to-emerald-700 transition-all shadow-sm"
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
            <button @click="close" class="text-white/70 hover:text-white">
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
            <p class="text-sm text-gray-500 text-center">تم استخراج رقم اللوحة:</p>
            <input
              v-model="editedPlate"
              class="w-full text-center font-mono text-2xl font-bold tracking-widest border-2 border-teal-500 rounded-xl py-3 focus:outline-none focus:ring-2 focus:ring-teal-400 uppercase"
              placeholder="تحقق وعدّل إذا لزم"
              @input="editedPlate = editedPlate.toUpperCase()"
            />
            <p class="text-xs text-gray-400 text-center">راجع الرقم وعدّله إذا لزم الأمر ثم اضغط تأكيد</p>
            <div class="flex gap-2">
              <button @click="confirm" :disabled="!editedPlate.trim()"
                class="flex-1 bg-teal-600 disabled:opacity-50 hover:bg-teal-700 text-white rounded-xl py-2.5 text-sm font-medium transition-colors">
                ✓ تأكيد واستخدام اللوحة
              </button>
              <button @click="retake" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl py-2.5 text-sm transition-colors">
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
            <button @click="capture"
              class="w-16 h-16 rounded-full bg-white border-4 border-teal-400 hover:bg-teal-50 transition-colors flex items-center justify-center shadow-lg">
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
import { ref, onUnmounted } from 'vue'
import { CameraIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits<{ (e: 'plate', value: string): void }>()

const visible     = ref(false)
const captured    = ref(false)
const processing  = ref(false)
const extracted   = ref(false)
const editedPlate = ref('')
const error       = ref('')

const videoRef      = ref<HTMLVideoElement | null>(null)
const canvasRef     = ref<HTMLCanvasElement | null>(null)
const processCanvas = ref<HTMLCanvasElement | null>(null)
let stream: MediaStream | null = null

async function open() {
  error.value     = ''
  captured.value  = false
  extracted.value = false
  editedPlate.value = ''
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

function capture() {
  if (!videoRef.value || !canvasRef.value) return
  const v = videoRef.value
  const c = canvasRef.value
  c.width  = v.videoWidth
  c.height = v.videoHeight
  c.getContext('2d')!.drawImage(v, 0, 0)
  stopCamera()
  captured.value = true
  processImage(c)
}

async function onFileUpload(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  captured.value = true
  const img = new Image()
  const url  = URL.createObjectURL(file)
  img.onload = async () => {
    const c = processCanvas.value!
    c.width  = img.width
    c.height = img.height
    c.getContext('2d')!.drawImage(img, 0, 0)
    URL.revokeObjectURL(url)
    processImage(c)
  }
  img.src = url
}

async function processImage(canvas: HTMLCanvasElement) {
  processing.value = true
  error.value = ''
  try {
    const base64 = canvas.toDataURL('image/jpeg', 0.85).split(',')[1]
    const token  = localStorage.getItem('auth_token') ?? ''
    const res    = await fetch('/api/v1/ocr/plate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body: JSON.stringify({ image: base64 }),
    })
    if (res.ok) {
      const json = await res.json()
      editedPlate.value = (json.plate ?? '').toUpperCase()
    } else {
      editedPlate.value = ''
    }
  } catch {
    editedPlate.value = ''
  } finally {
    processing.value = false
    extracted.value  = true
  }
}

function confirm() {
  const p = editedPlate.value.trim().toUpperCase()
  if (!p) return
  emit('plate', p)
  close()
}

function retake() {
  captured.value  = false
  extracted.value = false
  editedPlate.value = ''
  error.value = ''
  startCamera()
}

function close() {
  stopCamera()
  visible.value   = false
  captured.value  = false
  extracted.value = false
  editedPlate.value = ''
  error.value = ''
}

onUnmounted(stopCamera)
</script>

<style scoped>
.border-3 { border-width: 3px; }
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
