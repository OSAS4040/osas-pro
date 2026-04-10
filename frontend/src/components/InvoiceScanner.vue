<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="open" class="fixed inset-0 bg-black/60 z-[9998] flex items-center justify-center p-4" dir="rtl" @click.self="close">
        <div class="bg-white dark:bg-slate-800 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] flex flex-col">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex-shrink-0">
            <div class="flex items-center gap-2">
              <DocumentArrowUpIcon class="w-5 h-5 text-primary-600" />
              <h3 class="font-bold text-gray-900 dark:text-slate-100">مسح فواتير المشتريات</h3>
              <span class="text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 px-2 py-0.5 rounded-full">يدعم PDF, JPG, PNG</span>
            </div>
            <button class="p-1.5 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg" @click="close">
              <XMarkIcon class="w-5 h-5 text-gray-400" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto p-6 space-y-5">
            <!-- Step 1: Upload -->
            <div v-if="step === 'upload'">
              <label
                class="flex flex-col items-center justify-center border-2 border-dashed rounded-2xl p-10 cursor-pointer transition-colors"
                :class="dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-slate-600 hover:border-primary-400'"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="onDrop"
              >
                <CloudArrowUpIcon class="w-12 h-12 text-gray-300 dark:text-slate-600 mb-3" />
                <p class="text-sm font-medium text-gray-700 dark:text-slate-300">اسحب الفواتير هنا أو انقر للاختيار</p>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">يدعم رفع عدة ملفات دفعة واحدة</p>
                <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple class="hidden" @change="onFileChange" />
              </label>

              <!-- File list -->
              <div v-if="files.length" class="mt-4 space-y-2">
                <div v-for="(f, i) in files" :key="i"
                     class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-slate-700 rounded-xl border border-gray-200 dark:border-slate-600"
                >
                  <DocumentTextIcon class="w-8 h-8 text-primary-400 flex-shrink-0" />
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 dark:text-slate-200 truncate">{{ f.name }}</p>
                    <p class="text-xs text-gray-400 dark:text-slate-500">{{ (f.size / 1024).toFixed(0) }} KB</p>
                  </div>
                  <button class="text-gray-400 hover:text-red-500" @click="files.splice(i, 1)"><XMarkIcon class="w-4 h-4" /></button>
                </div>
              </div>

              <button v-if="files.length" class="w-full mt-4 py-3 bg-primary-600 text-white rounded-xl text-sm font-semibold hover:bg-primary-700 flex items-center justify-center gap-2"
                      @click="processFiles"
              >
                <SparklesIcon class="w-4 h-4" />
                استخراج البيانات من {{ files.length }} {{ files.length === 1 ? 'فاتورة' : 'فواتير' }}
              </button>
            </div>

            <!-- Step 2: Processing -->
            <div v-else-if="step === 'processing'" class="text-center py-12 space-y-4">
              <div class="w-16 h-16 mx-auto relative">
                <div class="absolute inset-0 border-4 border-primary-100 rounded-full" />
                <div class="absolute inset-0 border-4 border-primary-600 border-t-transparent rounded-full animate-spin" />
              </div>
              <p class="text-lg font-bold text-gray-800 dark:text-slate-200">جارٍ قراءة الفواتير...</p>
              <p class="text-sm text-gray-400 dark:text-slate-500">{{ processMsg }}</p>
              <div class="w-48 mx-auto bg-gray-100 dark:bg-slate-700 rounded-full h-1.5">
                <div class="bg-primary-600 h-1.5 rounded-full transition-all" :style="{ width: processProgress + '%' }" />
              </div>
            </div>

            <!-- Step 3: Review Extracted Data -->
            <div v-else-if="step === 'review'" class="space-y-5">
              <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                <CheckCircleIcon class="w-5 h-5" />
                <p class="text-sm font-semibold">تم استخراج بيانات {{ extracted.length }} فاتورة — راجع قبل التسجيل</p>
              </div>

              <div v-for="(inv, idx) in extracted" :key="idx"
                   class="border border-gray-200 dark:border-slate-600 rounded-xl overflow-hidden"
              >
                <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-slate-700">
                  <p class="text-sm font-semibold text-gray-800 dark:text-slate-200">فاتورة {{ idx + 1 }}: {{ inv.fileName }}</p>
                  <button class="text-xs text-red-500 hover:underline" @click="extracted.splice(idx, 1)">حذف</button>
                </div>
                <div class="p-4 space-y-3">
                  <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">المورد</label>
                      <input v-model="inv.supplier_name" class="field" placeholder="اسم المورد" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">رقم الفاتورة</label>
                      <input v-model="inv.invoice_number" class="field font-mono" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">تاريخ الفاتورة</label>
                      <SmartDatePicker :model-value="inv.invoice_date" mode="single" @change="(val) => onInvoiceDateChange(inv, val)" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">الإجمالي (قبل الضريبة)</label>
                      <input v-model="inv.subtotal" type="number" step="0.01" class="field font-mono" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">الضريبة (VAT)</label>
                      <input v-model="inv.tax_amount" type="number" step="0.01" class="field font-mono" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">الإجمالي الكلي</label>
                      <input v-model="inv.total" type="number" step="0.01" class="field font-mono font-bold" />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-500 dark:text-slate-400 mb-1">الرقم الضريبي للمورد</label>
                      <input v-model="inv.supplier_vat" class="field font-mono" placeholder="3XXXXXXXXXX" />
                    </div>
                  </div>

                  <!-- Items preview -->
                  <div v-if="inv.items.length">
                    <p class="text-xs font-semibold text-gray-600 dark:text-slate-400 mb-2">البنود المستخرجة ({{ inv.items.length }})</p>
                    <div class="space-y-1.5 max-h-36 overflow-y-auto">
                      <div v-for="(item, j) in inv.items" :key="j"
                           class="flex items-center gap-2 text-xs"
                      >
                        <input v-model="item.description" class="flex-1 px-2 py-1 border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 dark:text-slate-200" />
                        <input v-model="item.qty" type="number" class="w-14 px-2 py-1 border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 dark:text-slate-200 text-center" />
                        <input v-model="item.unit_price" type="number" step="0.01" class="w-20 px-2 py-1 border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-900 dark:text-slate-200 font-mono" />
                        <button class="text-red-400" @click="inv.items.splice(j, 1)"><XMarkIcon class="w-3 h-3" /></button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="submitError" class="text-red-600 dark:text-red-400 text-sm bg-red-50 dark:bg-red-900/20 rounded-xl p-3">{{ submitError }}</div>

              <div class="flex gap-3 justify-end">
                <button class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-xl text-sm dark:text-slate-300" @click="step = 'upload'; files = []">رفع مزيد</button>
                <button :disabled="submitting || !extracted.length" class="px-6 py-2 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 disabled:opacity-50 flex items-center gap-2"
                        @click="submitAll"
                >
                  <CheckCircleIcon class="w-4 h-4" />
                  {{ submitting ? 'جارٍ التسجيل...' : `تسجيل ${extracted.length} فاتورة` }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import {
  DocumentArrowUpIcon, XMarkIcon, CloudArrowUpIcon, DocumentTextIcon,
  CheckCircleIcon, SparklesIcon,
} from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

const toast = useToast()
const open  = ref(false)
const step  = ref<'upload' | 'processing' | 'review'>('upload')
const dragging = ref(false)
const files = ref<File[]>([])
const extracted = ref<any[]>([])
const processMsg = ref('يرجى الانتظار...')
const processProgress = ref(0)
const submitting = ref(false)
const submitError = ref('')

function show() { open.value = true; step.value = 'upload'; files.value = []; extracted.value = [] }
function close() { open.value = false }

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files) files.value.push(...Array.from(input.files))
}
function onDrop(e: DragEvent) {
  dragging.value = false
  if (e.dataTransfer?.files) files.value.push(...Array.from(e.dataTransfer.files))
}

async function processFiles() {
  step.value = 'processing'
  processProgress.value = 0
  extracted.value = []

  for (let i = 0; i < files.value.length; i++) {
    const f = files.value[i]
    processMsg.value = `جارٍ قراءة: ${f.name}`
    processProgress.value = Math.round(((i) / files.value.length) * 80)

    const inv = await extractFromFile(f)
    extracted.value.push(inv)
    processProgress.value = Math.round(((i + 1) / files.value.length) * 100)
  }

  step.value = 'review'
}

async function extractFromFile(f: File): Promise<any> {
  // Try server-side extraction first
  try {
    const fd = new FormData()
    fd.append('file', f)
    const r = await apiClient.post('/purchases/scan', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: 30000,
    })
    return { fileName: f.name, ...r.data.data }
  } catch {
    // Fallback: smart pattern extraction from filename + empty form
    return parseFromFilename(f)
  }
}

function parseFromFilename(f: File): any {
  const today = new Date().toISOString().split('T')[0]
  // Extract possible date/number from filename using regex
  const dateMatch = f.name.match(/(\d{4}[-/]\d{1,2}[-/]\d{1,2})/)
  const numMatch  = f.name.match(/(\d{4,10})/)
  return {
    fileName:       f.name,
    supplier_name:  '',
    invoice_number: numMatch?.[1] ?? '',
    invoice_date:   dateMatch?.[1] ?? today,
    subtotal:       '',
    tax_amount:     '',
    total:          '',
    supplier_vat:   '',
    items: [{ description: '', qty: 1, unit_price: '' }],
  }
}

function onInvoiceDateChange(inv: any, val: { from: string; to: string }) {
  inv.invoice_date = val.from || val.to
}

async function submitAll() {
  if (!extracted.value.length) return
  submitting.value = true
  submitError.value = ''
  try {
    await apiClient.post('/purchases/bulk', { purchases: extracted.value })
    toast.success(`تم تسجيل ${extracted.value.length} فاتورة بنجاح`)
    close()
  } catch (e: any) {
    submitError.value = e?.response?.data?.message ?? 'حدث خطأ أثناء التسجيل'
  } finally { submitting.value = false }
}

defineExpose({ show })
</script>

<style scoped>
.field { @apply w-full px-3 py-2 border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-gray-900 dark:text-slate-100 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent; }
.modal-fade-enter-active { transition: all 0.2s ease-out; }
.modal-fade-leave-active { transition: all 0.15s ease-in; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
