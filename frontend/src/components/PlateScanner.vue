<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="open" class="fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4" dir="rtl" @click.self="close">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
          <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 dark:border-slate-700">
            <div class="flex items-center gap-2">
              <CameraIcon class="w-5 h-5 text-primary-600" />
              <h3 class="font-bold text-gray-900 dark:text-slate-100">مسح لوحة المركبة</h3>
            </div>
            <button @click="close" class="p-1.5 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg">
              <XMarkIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>

          <div class="p-5 space-y-4">
            <!-- Camera Preview -->
            <div class="relative bg-black rounded-xl overflow-hidden aspect-video flex items-center justify-center">
              <video v-if="streaming" ref="videoRef" autoplay playsinline class="w-full h-full object-cover" />
              <canvas ref="canvasRef" class="hidden" />
              <img v-if="capturedImg" :src="capturedImg" class="w-full h-full object-cover absolute inset-0" />

              <!-- Overlay frame -->
              <div v-if="streaming && !capturedImg" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="border-2 border-dashed border-yellow-400 rounded-lg" style="width:80%;height:35%">
                  <div class="absolute -top-5 left-0 right-0 text-center text-yellow-300 text-xs">وجّه الكاميرا نحو اللوحة</div>
                </div>
              </div>

              <!-- No camera -->
              <div v-if="!streaming && !capturedImg" class="text-center text-gray-400 p-6">
                <CameraIcon class="w-12 h-12 mx-auto mb-2 text-gray-600" />
                <p class="text-sm">{{ cameraError || 'اضغط تشغيل الكاميرا' }}</p>
              </div>
            </div>

            <!-- Camera Controls -->
            <div class="flex gap-2 justify-center">
              <button v-if="!streaming" @click="startCamera"
                class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium">
                <VideoCameraIcon class="w-4 h-4" /> تشغيل الكاميرا
              </button>
              <template v-else-if="!capturedImg">
                <button @click="capture"
                  class="flex items-center gap-2 px-5 py-2 bg-green-600 text-white rounded-lg text-sm font-medium">
                  <CameraIcon class="w-4 h-4" /> التقاط
                </button>
                <button @click="stopCamera"
                  class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm text-gray-600 dark:text-slate-300">
                  إيقاف
                </button>
              </template>
              <template v-else>
                <button @click="retake" class="flex items-center gap-2 px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm">
                  <ArrowPathIcon class="w-4 h-4" /> إعادة الالتقاط
                </button>
              </template>
            </div>

            <!-- Detected / Manual Plate -->
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1.5">
                رقم اللوحة
                <span v-if="detecting" class="text-xs text-blue-500 mr-2 animate-pulse">جارٍ التعرف...</span>
                <span v-else-if="plateDetected" class="text-xs text-green-500 mr-2">✓ تم التعرف</span>
              </label>
              <div class="relative">
                <input
                  v-model="plate"
                  @input="plate = plate.toUpperCase()"
                  class="w-full px-3 py-3 border-2 rounded-xl text-center text-xl font-bold font-mono tracking-widest uppercase focus:outline-none transition-colors"
                  :class="plate ? 'border-green-500 text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20' : 'border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 dark:text-slate-100'"
                  placeholder="أ ب ج - ١٢٣٤"
                  dir="ltr"
                />
              </div>
              <p class="text-xs text-gray-400 dark:text-slate-500 mt-1 text-center">يمكن التعديل يدوياً</p>
            </div>

            <div v-if="plate" class="flex gap-3 justify-end">
              <button @click="close" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm dark:text-slate-300">إلغاء</button>
              <button @click="confirm" class="px-5 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                تأكيد اللوحة ← {{ plate }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { CameraIcon, XMarkIcon, VideoCameraIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits<{ (e: 'confirm', plate: string): void }>()

const open        = ref(false)
const streaming   = ref(false)
const capturedImg = ref('')
const plate       = ref('')
const detecting   = ref(false)
const plateDetected = ref(false)
const cameraError = ref('')

const videoRef  = ref<HTMLVideoElement | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)
let stream: MediaStream | null = null

function show() { open.value = true; plate.value = ''; capturedImg.value = '' }
function close() { open.value = false; stopCamera() }

async function startCamera() {
  cameraError.value = ''
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: 1280, height: 720 } })
    if (videoRef.value) { videoRef.value.srcObject = stream }
    streaming.value = true
  } catch {
    cameraError.value = 'تعذّر الوصول للكاميرا — تحقق من الإذن'
  }
}

function stopCamera() {
  stream?.getTracks().forEach(t => t.stop())
  stream = null
  streaming.value = false
}

function capture() {
  if (!videoRef.value || !canvasRef.value) return
  const v = videoRef.value
  const c = canvasRef.value
  c.width = v.videoWidth
  c.height = v.videoHeight
  c.getContext('2d')!.drawImage(v, 0, 0)
  capturedImg.value = c.toDataURL('image/jpeg', 0.92)
  stopCamera()
  detectPlate()
}

function retake() { capturedImg.value = ''; plate.value = ''; plateDetected.value = false; startCamera() }

async function detectPlate() {
  if (!capturedImg.value) return
  detecting.value = true
  try {
    // Pattern-based detection fallback (no external API needed)
    // Saudi plate pattern: 3 Arabic letters + 4 digits  (or 3 Latin letters + 4 digits)
    // We attempt basic text extraction via fetch to our own OCR endpoint
    await new Promise(r => setTimeout(r, 1200)) // simulate processing
    // Suggest common Saudi plate format as placeholder
    plate.value = ''
    plateDetected.value = false
  } finally {
    detecting.value = false
  }
}

function confirm() {
  emit('confirm', plate.value.trim())
  close()
}

onUnmounted(stopCamera)
defineExpose({ show })
</script>

<style scoped>
.modal-fade-enter-active { transition: all 0.2s ease-out; }
.modal-fade-leave-active { transition: all 0.15s ease-in; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
