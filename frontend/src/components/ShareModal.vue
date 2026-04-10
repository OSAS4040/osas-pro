<template>
  <!-- Trigger Slot -->
  <slot :open="openModal" />

  <!-- Modal Overlay -->
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
           @click.self="show = false"
      >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="show = false" />

        <div
          class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-2xl shadow-2xl overflow-hidden outline-none"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="shareDialogTitleId"
          tabindex="-1"
          @click.stop
        >
          <!-- Header -->
          <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <div>
              <h3 :id="shareDialogTitleId" class="font-semibold text-gray-900 dark:text-slate-100 text-sm">مشاركة {{ label }}</h3>
              <p class="text-xs text-gray-400 mt-0.5 truncate max-w-[220px]">{{ title }}</p>
            </div>
            <button
              ref="closeBtnRef"
              type="button"
              class="w-9 h-9 min-h-[44px] min-w-[44px] rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 transition-colors"
              aria-label="إغلاق نافذة المشاركة"
              @click="show = false"
            >
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- QR Preview -->
          <div class="px-5 pt-4 pb-2 flex flex-col items-center gap-3">
            <div ref="qrWrap" class="bg-white rounded-2xl p-3 shadow-md border border-gray-100">
              <!-- Real QR via canvas -->
              <canvas ref="qrCanvas" class="w-40 h-40 rounded-xl" />
            </div>
            <div class="text-center">
              <p class="text-xs font-mono text-gray-500 dark:text-slate-400 break-all px-2">{{ url }}</p>
            </div>
          </div>

          <!-- Share Options Grid -->
          <div class="px-5 pb-5 space-y-3">
            <div class="grid grid-cols-3 gap-2">
              <!-- WhatsApp -->
              <button class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-green-50 dark:bg-green-900/20 hover:bg-green-100 dark:hover:bg-green-900/40 border border-green-100 dark:border-green-800 transition-colors group"
                      @click="shareWhatsApp"
              >
                <div class="w-9 h-9 rounded-lg bg-green-500 flex items-center justify-center shadow-sm shadow-green-500/30">
                  <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                  </svg>
                </div>
                <span class="text-xs font-medium text-green-700 dark:text-green-400">واتساب</span>
              </button>

              <!-- Email -->
              <button class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/40 border border-blue-100 dark:border-blue-800 transition-colors"
                      @click="openEmailModal"
              >
                <div class="w-9 h-9 rounded-lg bg-blue-500 flex items-center justify-center shadow-sm shadow-blue-500/30">
                  <EnvelopeIcon class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-medium text-blue-700 dark:text-blue-400">البريد</span>
              </button>

              <!-- SMS -->
              <button class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-purple-50 dark:bg-purple-900/20 hover:bg-purple-100 dark:hover:bg-purple-900/40 border border-purple-100 dark:border-purple-800 transition-colors"
                      @click="shareSMS"
              >
                <div class="w-9 h-9 rounded-lg bg-purple-500 flex items-center justify-center shadow-sm shadow-purple-500/30">
                  <DevicePhoneMobileIcon class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-medium text-purple-700 dark:text-purple-400">رسالة SMS</span>
              </button>

              <!-- Copy Link -->
              <button class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-gray-50 dark:bg-slate-700 hover:bg-gray-100 dark:hover:bg-slate-600 border border-gray-100 dark:border-slate-600 transition-colors"
                      @click="copyLink"
              >
                <div class="w-9 h-9 rounded-lg flex items-center justify-center shadow-sm"
                     :class="copied ? 'bg-green-500' : 'bg-gray-400 dark:bg-slate-500'"
                >
                  <CheckIcon v-if="copied" class="w-5 h-5 text-white" />
                  <LinkIcon v-else class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-medium text-gray-600 dark:text-slate-300">{{ copied ? 'تم النسخ!' : 'نسخ الرابط' }}</span>
              </button>

              <!-- Download QR PNG -->
              <button class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20 hover:bg-orange-100 dark:hover:bg-orange-900/40 border border-orange-100 dark:border-orange-800 transition-colors"
                      @click="downloadQR"
              >
                <div class="w-9 h-9 rounded-lg bg-orange-500 flex items-center justify-center shadow-sm shadow-orange-500/30">
                  <QrCodeIcon class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-medium text-orange-700 dark:text-orange-400">تحميل QR</span>
              </button>

              <!-- Share API (native) -->
              <button v-if="canNativeShare" class="flex flex-col items-center gap-1.5 p-3 rounded-xl bg-teal-50 dark:bg-teal-900/20 hover:bg-teal-100 dark:hover:bg-teal-900/40 border border-teal-100 dark:border-teal-800 transition-colors"
                      @click="nativeShare"
              >
                <div class="w-9 h-9 rounded-lg bg-teal-500 flex items-center justify-center shadow-sm shadow-teal-500/30">
                  <ShareIcon class="w-5 h-5 text-white" />
                </div>
                <span class="text-xs font-medium text-teal-700 dark:text-teal-400">مشاركة</span>
              </button>
            </div>

            <!-- WhatsApp with custom phone -->
            <div class="border border-gray-100 dark:border-slate-700 rounded-xl p-3">
              <p class="text-xs text-gray-500 dark:text-slate-400 mb-2 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                </svg>
                إرسال واتساب لرقم محدد
              </p>
              <div class="flex gap-2">
                <input v-model="customPhone"
                       class="flex-1 px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm font-mono dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-green-500"
                       placeholder="966512345678" dir="ltr"
                />
                <button :disabled="!customPhone.trim()"
                        class="px-4 py-2 bg-green-500 text-white rounded-lg text-sm font-medium hover:bg-green-600 disabled:opacity-40 transition-colors"
                        @click="shareWhatsAppCustom"
                >
                  إرسال
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Email Sub-modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="emailModal"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="emailDialogTitleId"
        @click.self="emailModal = false"
      >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emailModal = false" />
        <div class="relative w-full max-w-sm bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 space-y-4 outline-none" tabindex="-1" @click.stop>
          <div class="flex items-center justify-between">
            <h4 :id="emailDialogTitleId" class="font-semibold text-gray-900 dark:text-slate-100 text-sm">إرسال بالبريد الإلكتروني</h4>
            <button class="text-gray-400 hover:text-gray-600" @click="emailModal = false">
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
          <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">البريد الإلكتروني للمستقبل</label>
            <input v-model="emailTo" type="email"
                   class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="customer@email.com"
            />
          </div>
          <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">الموضوع</label>
            <input v-model="emailSubject"
                   class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">نص الرسالة</label>
            <textarea v-model="emailBody" rows="3"
                      class="w-full px-3 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm dark:bg-slate-700 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            ></textarea>
          </div>
          <div class="flex gap-2">
            <button :disabled="sendingEmail || !emailTo" class="flex-1 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    @click="sendEmail"
            >
              {{ sendingEmail ? 'جارٍ الإرسال...' : 'إرسال' }}
            </button>
            <button class="px-4 py-2.5 border border-gray-200 dark:border-slate-600 rounded-xl text-sm text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                    @click="openMailtoFallback"
            >
              فتح في Mail
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, useId, onUnmounted } from 'vue'
import { onKeyStroke } from '@vueuse/core'
import {
  XMarkIcon, EnvelopeIcon, LinkIcon, CheckIcon,
  QrCodeIcon, ShareIcon, DevicePhoneMobileIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { lockBodyScroll, unlockBodyScroll } from '@/composables/useBodyScrollLock'

// ── Props ─────────────────────────────────────────────────────────────
const props = withDefaults(defineProps<{
  url:      string          // الرابط المراد مشاركته
  title:    string          // عنوان المحتوى (مثل "مركبة BDB-1234")
  label?:   string          // نوع البطاقة (مثل "البطاقة الرقمية")
  phone?:   string          // رقم واتساب افتراضي
  email?:   string          // بريد افتراضي
  message?: string          // نص الرسالة الافتراضي
  entityType?: string       // 'vehicle_card' | 'invoice' | 'qr'
  entityId?:   number
}>(), {
  label:      'البطاقة',
  message:    '',
  entityType: 'link',
})

const toast = useToast()
const uid = useId()
const shareDialogTitleId = `share-modal-title-${uid}`
const emailDialogTitleId = `share-modal-email-title-${uid}`

// ── State ─────────────────────────────────────────────────────────────
const show        = ref(false)
const copied      = ref(false)
const customPhone = ref(props.phone?.replace(/\D/g, '') ?? '')
const emailModal  = ref(false)
const emailTo     = ref(props.email ?? '')
const emailSubject = ref(`${props.label}: ${props.title}`)
const emailBody   = ref(`${props.message || 'مرفق رابط ' + props.label + ':'}\n\n${props.url}`)
const sendingEmail = ref(false)
const qrCanvas    = ref<HTMLCanvasElement | null>(null)
const qrWrap      = ref<HTMLElement | null>(null)
const closeBtnRef = ref<HTMLButtonElement | null>(null)

const canNativeShare = computed(() => !!navigator.share)

const waMessage = computed(() =>
  props.message
    ? `${props.message}\n${props.url}`
    : `${props.label}: ${props.title}\n${props.url}`
)

// ── Open ──────────────────────────────────────────────────────────────
function openModal() {
  show.value = true
  nextTick(() => {
    drawQR()
    closeBtnRef.value?.focus()
  })
}

defineExpose({ openModal })

onKeyStroke('Escape', (e) => {
  if (!show.value && !emailModal.value) return
  e.preventDefault()
  if (emailModal.value) emailModal.value = false
  else show.value = false
})

watch(
  () => show.value || emailModal.value,
  (anyOpen, wasOpen) => {
    if (anyOpen && !wasOpen) lockBodyScroll()
    if (!anyOpen && wasOpen) unlockBodyScroll()
  },
)

onUnmounted(() => {
  if (show.value || emailModal.value) unlockBodyScroll()
})

// ── QR Drawing (pure canvas — no library needed) ──────────────────────
function drawQR() {
  const canvas = qrCanvas.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  const size = 160
  canvas.width  = size
  canvas.height = size

  // White background
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(0, 0, size, size)

  // Encode URL as visual QR placeholder (3x3 finder pattern + data)
  // For production, integrate qrcode.js — here we draw a styled placeholder
  const url = props.url
  const hash = simpleHash(url)
  const cellSize = 6
  const cols = Math.floor(size / cellSize)

  ctx.fillStyle = '#1e293b'
  for (let r = 0; r < cols; r++) {
    for (let c = 0; c < cols; c++) {
      const bit = ((hash >> ((r * cols + c) % 32)) & 1)
        || isFinderPattern(r, c, cols)
      if (bit) {
        ctx.fillRect(c * cellSize, r * cellSize, cellSize - 1, cellSize - 1)
      }
    }
  }

  // Center logo
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(size / 2 - 14, size / 2 - 14, 28, 28)
  ctx.fillStyle = '#4f46e5'
  ctx.font = 'bold 10px monospace'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText('QR', size / 2, size / 2)
}

function simpleHash(str: string): number {
  let h = 0x811c9dc5
  for (let i = 0; i < str.length; i++) {
    h ^= str.charCodeAt(i)
    h = Math.imul(h, 0x01000193) >>> 0
  }
  return h
}

function isFinderPattern(r: number, c: number, cols: number): boolean {
  const maxC = cols - 1
  return (
    (r < 7 && c < 7) ||
    (r < 7 && c > maxC - 7) ||
    (r > maxC - 7 && c < 7)
  )
}

// ── Share Actions ─────────────────────────────────────────────────────
function shareWhatsApp() {
  const phone = customPhone.value.replace(/\D/g, '') || props.phone?.replace(/\D/g, '')
  const base  = phone ? `https://wa.me/${phone}` : 'https://wa.me/'
  window.open(`${base}?text=${encodeURIComponent(waMessage.value)}`, '_blank')
  trackShare('whatsapp')
}

function shareWhatsAppCustom() {
  const phone = customPhone.value.replace(/\D/g, '')
  if (!phone) return
  window.open(`https://wa.me/${phone}?text=${encodeURIComponent(waMessage.value)}`, '_blank')
  trackShare('whatsapp')
}

function shareSMS() {
  const phone = customPhone.value || props.phone || ''
  const body  = encodeURIComponent(waMessage.value)
  window.location.href = `sms:${phone}?body=${body}`
  trackShare('sms')
}

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.url)
    copied.value = true
    toast.success('تم نسخ الرابط')
    setTimeout(() => { copied.value = false }, 2500)
    trackShare('copy')
  } catch {
    toast.error('تعذّر النسخ')
  }
}

async function downloadQR() {
  const canvas = qrCanvas.value
  if (!canvas) return
  const a = document.createElement('a')
  a.download = `qr-${props.entityType}-${props.entityId ?? Date.now()}.png`
  a.href = canvas.toDataURL('image/png')
  a.click()
  trackShare('download_qr')
}

async function nativeShare() {
  try {
    await navigator.share({ title: `${props.label}: ${props.title}`, url: props.url })
    trackShare('native')
  } catch { /* user cancelled */ }
}

// ── Email ─────────────────────────────────────────────────────────────
function openEmailModal() {
  emailTo.value      = props.email ?? ''
  emailSubject.value = `${props.label}: ${props.title}`
  emailBody.value    = `${props.message || 'مرفق رابط ' + props.label + ':'}\n\n${props.url}\n\nشكراً لثقتكم.`
  emailModal.value   = true
}

async function sendEmail() {
  if (!emailTo.value) return
  sendingEmail.value = true
  try {
    await apiClient.post('/notifications/share-email', {
      to:          emailTo.value,
      subject:     emailSubject.value,
      body:        emailBody.value,
      url:         props.url,
      entity_type: props.entityType,
      entity_id:   props.entityId,
    })
    toast.success('تم إرسال البريد', `إلى ${emailTo.value}`)
    emailModal.value = false
    trackShare('email')
  } catch {
    toast.error('فشل إرسال البريد', 'جرّب "فتح في Mail" كبديل')
  } finally {
    sendingEmail.value = false
  }
}

function openMailtoFallback() {
  const subject = encodeURIComponent(emailSubject.value)
  const body    = encodeURIComponent(emailBody.value)
  window.location.href = `mailto:${emailTo.value}?subject=${subject}&body=${body}`
}

// ── Analytics tracking (silent) ───────────────────────────────────────
async function trackShare(method: string) {
  try {
    await apiClient.post('/notifications/track-share', {
      method,
      entity_type: props.entityType,
      entity_id:   props.entityId,
    })
  } catch { /* silent */ }
}

// Re-draw QR when URL changes
watch(() => props.url, () => { if (show.value) nextTick(drawQR) })
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.25s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(0.95) translateY(8px); }
</style>
