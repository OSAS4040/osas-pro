<template>
  <div>
    <button class="flex items-center gap-2 px-4 py-2 border border-green-300 bg-green-50 text-green-700 rounded-lg text-sm font-medium hover:bg-green-100 transition-colors"
            @click="visible = true"
    >
      <ArrowUpTrayIcon class="w-4 h-4" />
      {{ label }}
    </button>

    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="visible" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" dir="rtl">
          <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl">
            <!-- Header -->
            <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
              <div>
                <h3 class="font-bold text-lg text-gray-900">{{ title }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">ارفع ملف Excel (.xlsx) أو CSV</p>
              </div>
              <button @click="close"><XMarkIcon class="w-5 h-5 text-gray-400" /></button>
            </div>

            <div class="p-6 space-y-5">
              <!-- Download Template -->
              <div class="flex items-center justify-between bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                <div>
                  <p class="text-sm font-medium text-blue-800">نموذج Excel</p>
                  <p class="text-xs text-blue-500 mt-0.5">حمّل النموذج واملأه ثم ارفعه</p>
                </div>
                <button class="flex items-center gap-1.5 text-xs bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                        @click="downloadTemplate"
                >
                  <ArrowDownTrayIcon class="w-3.5 h-3.5" />
                  تحميل النموذج
                </button>
              </div>

              <!-- File Drop Zone -->
              <div
                class="border-2 border-dashed rounded-xl p-6 text-center transition-colors cursor-pointer"
                :class="[dragOver ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-green-300 hover:bg-gray-50',
                         file ? 'border-green-400 bg-green-50' : '']"
                @click="fileInput?.click()"
                @dragover.prevent="dragOver = true"
                @dragleave="dragOver = false"
                @drop.prevent="onDrop"
              >
                <template v-if="!file">
                  <DocumentArrowUpIcon class="w-10 h-10 text-gray-300 mx-auto mb-2" />
                  <p class="text-sm text-gray-600">اسحب الملف هنا أو اضغط للاختيار</p>
                  <p class="text-xs text-gray-400 mt-1">.xlsx, .xls, .csv</p>
                </template>
                <template v-else>
                  <CheckCircleIcon class="w-10 h-10 text-green-500 mx-auto mb-2" />
                  <p class="text-sm font-medium text-green-700">{{ file.name }}</p>
                  <p class="text-xs text-gray-400 mt-1">{{ (file.size / 1024).toFixed(1) }} KB</p>
                </template>
                <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFile" />
              </div>

              <!-- Preview Table -->
              <div v-if="preview.length" class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-2 text-xs text-gray-500 flex justify-between">
                  <span>معاينة أول {{ Math.min(preview.length, 5) }} صفوف</span>
                  <span class="text-indigo-600 font-medium">{{ totalRows }} سجل في الملف</span>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b">
                      <tr>
                        <th v-for="col in columns" :key="col" class="px-3 py-2 text-right text-gray-500 font-medium whitespace-nowrap">{{ col }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(row, i) in preview.slice(0,5)" :key="i" class="border-b border-gray-50 hover:bg-gray-50">
                        <td v-for="col in columns" :key="col" class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ row[col] ?? '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Errors -->
              <div v-if="errors.length" class="bg-red-50 border border-red-100 rounded-xl p-3 space-y-1">
                <p class="text-xs font-semibold text-red-700">أخطاء في الملف ({{ errors.length }})</p>
                <p v-for="(e, i) in errors.slice(0, 5)" :key="i" class="text-xs text-red-600">• {{ e }}</p>
                <p v-if="errors.length > 5" class="text-xs text-red-400">...و {{ errors.length - 5 }} خطأ آخر</p>
              </div>

              <div v-if="success" class="bg-green-50 border border-green-100 rounded-xl p-3 text-sm text-green-700 text-center font-medium">
                ✅ تم استيراد {{ importedCount }} سجل بنجاح
              </div>
              <div v-if="serverError" class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-600 text-center">{{ serverError }}</div>

              <!-- Actions -->
              <div class="flex gap-3 pt-1">
                <button class="flex-1 border border-gray-200 text-gray-700 rounded-xl py-2.5 text-sm hover:bg-gray-50 transition-colors" @click="close">إلغاء</button>
                <button :disabled="!file || uploading || !preview.length" class="flex-1 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-xl py-2.5 text-sm font-medium transition-colors flex items-center justify-center gap-2"
                        @click="upload"
                >
                  <span v-if="uploading" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                  {{ uploading ? 'جاري الاستيراد...' : `استيراد ${totalRows} سجل` }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import {
  ArrowUpTrayIcon, XMarkIcon, ArrowDownTrayIcon,
  DocumentArrowUpIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps<{
  endpoint: string          // e.g. /api/v1/vehicles/import
  templateUrl: string       // e.g. /templates/vehicles.xlsx
  label?: string
  title?: string
}>()
const emit = defineEmits<{ (e: 'imported', count: number): void }>()

const visible    = ref(false)
const dragOver   = ref(false)
const uploading  = ref(false)
const file       = ref<File | null>(null)
const preview    = ref<Record<string, any>[]>([])
const columns    = ref<string[]>([])
const totalRows  = ref(0)
const errors     = ref<string[]>([])
const success    = ref(false)
const serverError= ref('')
const importedCount = ref(0)
const fileInput  = ref<HTMLInputElement | null>(null)

function onFile(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0]
  if (f) setFile(f)
}
function onDrop(e: DragEvent) {
  dragOver.value = false
  const f = e.dataTransfer?.files?.[0]
  if (f) setFile(f)
}

async function setFile(f: File) {
  file.value = f
  preview.value = []
  errors.value  = []
  success.value = false
  serverError.value = ''

  // Parse CSV for preview
  if (f.name.endsWith('.csv')) {
    const text  = await f.text()
    const lines = text.trim().split('\n').filter(Boolean)
    if (lines.length < 2) { errors.value = ['الملف فارغ أو لا يحتوي على بيانات']; return }
    const cols = lines[0].split(',').map(c => c.replace(/^"|"$/g, '').trim())
    columns.value   = cols
    totalRows.value = lines.length - 1
    preview.value   = lines.slice(1, 6).map(line => {
      const vals = line.split(',').map(v => v.replace(/^"|"$/g, '').trim())
      return Object.fromEntries(cols.map((c, i) => [c, vals[i] ?? '']))
    })
  } else {
    // For XLSX: just show file info (parsing XLSX needs a library)
    columns.value   = ['السجلات ستُستورد من الملف']
    totalRows.value = 0
    preview.value   = [{ 'السجلات ستُستورد من الملف': 'ارفع الملف للمعالجة' }]
  }
}

function downloadTemplate() {
  const a = document.createElement('a')
  a.href = props.templateUrl
  a.download = props.templateUrl.split('/').pop() ?? 'template.xlsx'
  a.click()
}

async function upload() {
  if (!file.value) return
  uploading.value   = true
  serverError.value = ''
  try {
    const fd    = new FormData()
    fd.append('file', file.value)
    const token = localStorage.getItem('auth_token') ?? ''
    const res   = await fetch(props.endpoint, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body: fd,
    })
    const json = await res.json()
    if (res.ok) {
      importedCount.value = json.imported ?? json.count ?? totalRows.value
      success.value = true
      emit('imported', importedCount.value)
    } else {
      serverError.value = json.message ?? 'فشل الاستيراد'
      if (json.errors) errors.value = Object.values(json.errors).flat() as string[]
    }
  } catch {
    serverError.value = 'خطأ في الاتصال — تحقق من الإنترنت'
  } finally {
    uploading.value = false
  }
}

function close() {
  visible.value = false
  file.value    = null
  preview.value = []
  columns.value = []
  totalRows.value = 0
  errors.value  = []
  success.value = false
  serverError.value = ''
}
</script>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
