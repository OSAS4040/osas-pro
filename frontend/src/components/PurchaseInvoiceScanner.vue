<template>
  <div>
    <!-- Trigger -->
    <button @click="visible = true"
      class="flex items-center gap-2 px-4 py-2 border border-indigo-300 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-100 transition-colors">
      <DocumentArrowUpIcon class="w-4 h-4" />
      مسح فواتير الشراء (OCR)
    </button>

    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="visible" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" dir="rtl">
          <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto shadow-2xl">

            <!-- Header -->
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between rounded-t-2xl z-10">
              <div>
                <h3 class="font-bold text-xl text-gray-900">مسح فواتير الشراء</h3>
                <p class="text-sm text-gray-400 mt-0.5">يمكنك رفع حتى 10 فواتير في وقت واحد</p>
              </div>
              <button @click="close"><XMarkIcon class="w-6 h-6 text-gray-400 hover:text-gray-700" /></button>
            </div>

            <div class="p-6 space-y-6">

              <!-- Drop Zone -->
              <div
                class="border-2 border-dashed rounded-2xl p-8 text-center transition-colors cursor-pointer"
                :class="dragOver ? 'border-indigo-400 bg-indigo-50' : 'border-gray-300 hover:border-indigo-300 hover:bg-gray-50'"
                @click="fileInput?.click()"
                @dragover.prevent="dragOver = true"
                @dragleave="dragOver = false"
                @drop.prevent="onDrop"
              >
                <DocumentArrowUpIcon class="w-12 h-12 text-indigo-300 mx-auto mb-3" />
                <p class="font-medium text-gray-700">اسحب الصور هنا أو اضغط للرفع</p>
                <p class="text-sm text-gray-400 mt-1">JPG, PNG, PDF — حتى 10 ملفات</p>
                <p v-if="items.length" class="text-xs text-indigo-600 mt-2">{{ items.length }} / 10 ملف مضاف</p>
                <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onFiles" />
              </div>

              <!-- Preview Grid -->
              <div v-if="items.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="(item, idx) in items" :key="idx"
                  class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">

                  <!-- Image Preview -->
                  <div class="relative h-32 bg-gray-50">
                    <img :src="item.preview" class="w-full h-full object-contain" />
                    <button @click="removeItem(idx)"
                      class="absolute top-2 left-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600">
                      ×
                    </button>
                    <div v-if="item.processing" class="absolute inset-0 bg-white/80 flex items-center justify-center">
                      <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <div v-if="item.done" class="absolute top-2 right-2 bg-green-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">✓</div>
                  </div>

                  <!-- Extracted Fields -->
                  <div class="p-3 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                      <div>
                        <label class="text-[10px] text-gray-400">رقم الفاتورة</label>
                        <input v-model="item.data.invoice_number" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="تلقائي..." />
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-400">التاريخ</label>
                        <input v-model="item.data.invoice_date" type="date" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" />
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-400">الإجمالي</label>
                        <input v-model="item.data.total" type="number" step="0.01" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="0.00" />
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-400">ضريبة القيمة المضافة</label>
                        <input v-model="item.data.vat_amount" type="number" step="0.01" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="0.00" />
                      </div>
                    </div>
                    <div>
                      <label class="text-[10px] text-gray-400">المورد</label>
                      <input v-model="item.data.supplier_name" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="اسم المورد..." />
                    </div>
                    <div v-if="item.error" class="text-xs text-red-500">{{ item.error }}</div>
                    <div v-if="item.saved" class="text-xs text-green-600 font-medium">✓ تم الحفظ</div>
                  </div>
                </div>
              </div>

              <!-- Scan Button -->
              <div v-if="items.length && !allScanned" class="flex justify-center">
                <button @click="scanAll" :disabled="scanning"
                  class="flex items-center gap-2 px-8 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl font-medium transition-colors shadow-sm">
                  <DocumentMagnifyingGlassIcon class="w-5 h-5" />
                  {{ scanning ? 'جاري تحليل الفواتير...' : `تحليل ${items.length} فاتورة بالذكاء الاصطناعي` }}
                </button>
              </div>

              <!-- Save All Button -->
              <div v-if="allScanned" class="flex items-center gap-3 justify-center">
                <p class="text-sm text-gray-500">تحقق من البيانات ثم احفظ</p>
                <button @click="saveAll" :disabled="saving"
                  class="flex items-center gap-2 px-8 py-3 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-xl font-medium transition-colors shadow-sm">
                  <CheckCircleIcon class="w-5 h-5" />
                  {{ saving ? 'جاري الحفظ...' : `حفظ ${items.length} فاتورة كمشتريات` }}
                </button>
              </div>

              <div v-if="saveError" class="text-sm text-red-600 text-center bg-red-50 rounded-xl p-3">{{ saveError }}</div>
              <div v-if="allSaved" class="text-sm text-green-600 text-center bg-green-50 rounded-xl p-4 font-medium">
                ✅ تم حفظ {{ items.length }} فاتورة بنجاح
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { DocumentArrowUpIcon, XMarkIcon, DocumentMagnifyingGlassIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const emit = defineEmits<{ (e: 'saved', invoices: any[]): void }>()

const visible   = ref(false)
const dragOver  = ref(false)
const scanning  = ref(false)
const saving    = ref(false)
const saveError = ref('')
const allSaved  = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

interface ScanItem {
  file: File
  preview: string
  base64: string
  processing: boolean
  done: boolean
  saved: boolean
  error: string
  data: {
    invoice_number: string
    invoice_date: string
    total: number | null
    vat_amount: number | null
    supplier_name: string
    vat_number: string
  }
}

const items = ref<ScanItem[]>([])

const allScanned = computed(() => items.value.length > 0 && items.value.every(i => i.done))

function makeItem(file: File): ScanItem {
  return {
    file, preview: URL.createObjectURL(file), base64: '',
    processing: false, done: false, saved: false, error: '',
    data: { invoice_number: '', invoice_date: '', total: null, vat_amount: null, supplier_name: '', vat_number: '' },
  }
}

async function toBase64(file: File): Promise<string> {
  return new Promise((res, rej) => {
    const fr = new FileReader()
    fr.onload  = () => res((fr.result as string).split(',')[1])
    fr.onerror = rej
    fr.readAsDataURL(file)
  })
}

function onFiles(e: Event) {
  const files = Array.from((e.target as HTMLInputElement).files ?? [])
  addFiles(files)
}
function onDrop(e: DragEvent) {
  dragOver.value = false
  const files = Array.from(e.dataTransfer?.files ?? []).filter(f => f.type.startsWith('image/'))
  addFiles(files)
}
function addFiles(files: File[]) {
  const remaining = 10 - items.value.length
  files.slice(0, remaining).forEach(f => items.value.push(makeItem(f)))
}
function removeItem(idx: number) { items.value.splice(idx, 1) }

async function scanAll() {
  scanning.value = true
  const token = localStorage.getItem('auth_token') ?? ''
  for (const item of items.value) {
    if (item.done) continue
    item.processing = true
    try {
      item.base64 = item.base64 || await toBase64(item.file)
      const res  = await fetch('/api/v1/ocr/invoice', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({ images: [item.base64] }),
      })
      const json = await res.json()
      const extracted = json.results?.[0] ?? {}
      item.data.invoice_number = extracted.invoice_number ?? ''
      item.data.invoice_date   = extracted.invoice_date   ?? ''
      item.data.total          = extracted.total          ?? null
      item.data.vat_amount     = extracted.vat_amount     ?? null
      item.data.supplier_name  = extracted.supplier_name  ?? ''
      item.data.vat_number     = extracted.vat_number     ?? ''
    } catch (e: any) {
      item.error = 'فشل التحليل — أدخل البيانات يدوياً'
    } finally {
      item.processing = false
      item.done = true
    }
  }
  scanning.value = false
}

async function saveAll() {
  saving.value    = true
  saveError.value = ''
  const token = localStorage.getItem('auth_token') ?? ''
  const saved: any[] = []
  for (const item of items.value) {
    if (item.saved) continue
    try {
      const res = await fetch('/api/v1/purchases', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
        body: JSON.stringify({
          reference_number:  item.data.invoice_number || `SCAN-${Date.now()}`,
          supplier_name:     item.data.supplier_name,
          invoice_date:      item.data.invoice_date || new Date().toISOString().slice(0,10),
          total:             item.data.total ?? 0,
          tax_amount:        item.data.vat_amount ?? 0,
          status:            'received',
          notes:             'مستورد عبر مسح الفاتورة (OCR)',
          source:            'ocr_scan',
        }),
      })
      if (res.ok) { item.saved = true; saved.push(await res.json()) }
      else        { item.error = 'فشل الحفظ' }
    } catch { item.error = 'خطأ في الاتصال' }
  }
  saving.value = false
  if (saved.length) { allSaved.value = true; emit('saved', saved) }
}

function close() {
  if (saving.value) return
  visible.value  = false
  allSaved.value = false
  saveError.value = ''
  items.value.forEach(i => URL.revokeObjectURL(i.preview))
  items.value = []
}
</script>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
