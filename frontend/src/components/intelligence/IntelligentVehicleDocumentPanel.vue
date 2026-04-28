<template>
  <div class="rounded-xl border border-primary-200 dark:border-primary-800/50 bg-primary-50/50 dark:bg-primary-950/20 p-4 space-y-3" dir="rtl">
    <h3 class="text-sm font-bold text-gray-800 dark:text-white flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-primary-500" />
      قراءة ذكية + أرشفة مستند
    </h3>
    <p class="text-xs text-gray-500 dark:text-slate-400">
      يُستخرج النص (Tesseract) ويُقترح نوع المستند والتواريخ. المعاينة أولاً — ثم تأكيد الأرشفة.
    </p>

    <input ref="fileRef" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="hidden" @change="onPick" />

    <div v-if="step === 'pick'" class="flex flex-wrap gap-2">
      <button type="button" class="btn btn-secondary text-xs py-2" @click="fileRef?.click()">
        اختر ملفاً (صورة أو PDF)
      </button>
    </div>

    <div v-else-if="step === 'preview'" class="space-y-3">
      <div class="text-xs space-y-1 bg-white dark:bg-slate-800 rounded-lg p-3 border border-gray-100 dark:border-slate-700">
        <p><span class="text-gray-400">النوع المقترح:</span> {{ classification?.type }} — {{ classification?.title }}</p>
        <p v-if="classification?.reference"><span class="text-gray-400">مرجع:</span> {{ classification.reference }}</p>
        <p v-if="classification?.expiry_date"><span class="text-gray-400">انتهاء:</span> {{ classification.expiry_date }}</p>
        <p v-if="classification?.confidence" class="text-gray-400">ثقة التصنيف: {{ classification.confidence }}</p>
      </div>
      <div v-if="previewMessage" class="text-xs text-amber-700 dark:text-amber-300/90">{{ previewMessage }}</div>
      <div class="flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary text-xs py-2" :disabled="archiving" @click="confirmArchive">
          {{ archiving ? 'جارٍ الأرشفة…' : 'تأكيد الأرشفة' }}
        </button>
        <button type="button" class="btn btn-secondary text-xs py-2" :disabled="archiving" @click="reset">
          إلغاء
        </button>
      </div>
    </div>

    <div v-else-if="step === 'done'" class="text-sm text-green-700 dark:text-green-400 font-medium">
      ✓ تم حفظ المستند في الأرشفة
      <button type="button" class="mr-2 text-xs text-primary-600 underline" @click="reset">رفع آخر</button>
    </div>

    <p v-if="err" class="text-xs text-red-600">{{ err }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import apiClient from '@/lib/apiClient'

const props = defineProps<{ vehicleId: number }>()

const fileRef = ref<HTMLInputElement | null>(null)
const step = ref<'pick' | 'preview' | 'done'>('pick')
const file = ref<File | null>(null)
const classification = ref<Record<string, unknown> | null>(null)
const previewMessage = ref('')
const err = ref('')
const archiving = ref(false)

async function onPick(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (!f) return
  err.value = ''
  file.value = f
  classification.value = null
  previewMessage.value = ''
  try {
    const fd = new FormData()
    fd.append('vehicle_id', String(props.vehicleId))
    fd.append('file', f)
    const { data } = await apiClient.post('/governance/ocr/vehicle-document', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    })
    if (data.preview) {
      classification.value = data.classification ?? null
      previewMessage.value = data.message ?? ''
      step.value = 'preview'
    } else {
      err.value = 'استجابة غير متوقعة من الخادم'
      step.value = 'pick'
      file.value = null
    }
  } catch (e: unknown) {
    err.value = 'تعذّر قراءة الملف — جرّب صورة أوضح أو أدخل البيانات يدوياً من إعدادات الوقود/المستندات.'
    step.value = 'pick'
    file.value = null
  }
}

async function confirmArchive() {
  if (!file.value) return
  err.value = ''
  archiving.value = true
  try {
    const fd = new FormData()
    fd.append('vehicle_id', String(props.vehicleId))
    fd.append('file', file.value)
    fd.append('confirm', '1')
    if (classification.value?.type) {
      fd.append('document_type', String(classification.value.type))
    }
    if (classification.value?.title) {
      fd.append('title', String(classification.value.title))
    }
    if (classification.value?.expiry_date) {
      fd.append('expiry_date', String(classification.value.expiry_date))
    }
    await apiClient.post('/governance/ocr/vehicle-document', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 120000,
    })
    step.value = 'done'
  } catch {
    err.value = 'فشل الأرشفة — تحقق من الصلاحيات أو حجم الملف.'
  } finally {
    archiving.value = false
  }
}

function reset() {
  step.value = 'pick'
  file.value = null
  classification.value = null
  err.value = ''
  if (fileRef.value) fileRef.value.value = ''
}
</script>
