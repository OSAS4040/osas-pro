<template>
  <div>
    <!-- Trigger -->
    <button class="flex items-center gap-2 px-4 py-2 border border-indigo-300 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-100 transition-colors"
            @click="visible = true"
    >
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
                <p class="font-medium text-gray-700">اسحب الملفات هنا أو اضغط للرفع</p>
                <p class="text-sm text-gray-400 mt-1">صور JPG / PNG / WebP أو PDF (تُحوَّل الصفحة الأولى إلى صورة للـ OCR)</p>
                <p v-if="items.length" class="text-xs text-indigo-600 mt-2">{{ items.length }} / 10 ملف مضاف</p>
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/jpg,application/pdf,.pdf"
                  multiple
                  class="hidden"
                  @change="onFiles"
                />
              </div>

              <!-- Preview Grid -->
              <div v-if="items.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="(item, idx) in items" :key="idx"
                     class="border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm"
                >
                  <!-- Image Preview -->
                  <div class="relative h-32 bg-gray-50">
                    <img :src="item.preview" class="w-full h-full object-contain" />
                    <button class="absolute top-2 left-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                            @click="removeItem(idx)"
                    >
                      ×
                    </button>
                    <div v-if="item.processing" class="absolute inset-0 bg-white/80 flex items-center justify-center">
                      <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <div
                      v-if="item.scanComplete && !item.error && hasUsefulExtraction(item.data)"
                      class="absolute top-2 right-2 bg-green-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                      title="استُخرجت بيانات"
                    >
                      ✓
                    </div>
                    <div
                      v-else-if="item.scanComplete && !item.error"
                      class="absolute top-2 right-2 bg-amber-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] font-bold"
                      title="راجع يدوياً"
                    >
                      ?
                    </div>
                  </div>

                  <!-- Extracted Fields -->
                  <div class="p-3 space-y-2">
                    <div>
                      <label class="text-[10px] text-gray-400">المورد (من النظام)</label>
                      <select v-model.number="item.data.supplier_id" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400 bg-white">
                        <option :value="0">— اختر مورداً للمراجعة قبل الحفظ —</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                      </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                      <div>
                        <label class="text-[10px] text-gray-400">رقم الفاتورة</label>
                        <input v-model="item.data.invoice_number" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="تلقائي..." />
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-400">التاريخ</label>
                        <SmartDatePicker :model-value="item.data.invoice_date" mode="single" @change="(val) => onInvoiceDateChange(item, val)" />
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-400">الإجمالي</label>
                        <input v-model.number="item.data.total" type="number" step="0.01" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="0.00" />
                      </div>
                      <div>
                        <label class="text-[10px] text-gray-400">ضريبة القيمة المضافة</label>
                        <input v-model.number="item.data.vat_amount" type="number" step="0.01" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="0.00" />
                      </div>
                    </div>
                    <div>
                      <label class="text-[10px] text-gray-400">اسم المورد (من الفاتورة)</label>
                      <input v-model="item.data.supplier_name" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-indigo-400" placeholder="للمطابقة اليدوية..." />
                    </div>
                    <div v-if="item.data.line_items?.length" class="border border-indigo-100 rounded-lg p-2 max-h-36 overflow-y-auto">
                      <p class="text-[10px] font-semibold text-indigo-700 mb-1">البنود المستخرجة + مطابقة المنتجات</p>
                      <div v-for="(line, li) in item.data.line_items" :key="li" class="text-[10px] py-1 border-b border-gray-100 last:border-0 flex justify-between gap-2">
                        <span class="text-gray-700 flex-1 truncate">{{ line.description }}</span>
                        <span :class="line.matched ? 'text-green-600 font-medium' : 'text-amber-600 font-medium'">
                          {{ line.matched ? 'مطابق' : 'غير مطابق' }}
                        </span>
                      </div>
                    </div>
                    <p v-if="item.scanComplete && !item.error && !hasUsefulExtraction(item.data)" class="text-[10px] text-amber-700 bg-amber-50 rounded px-2 py-1">
                      التحليل اكتمل لكن الحقول الظاهرة فارغة — راجع البنود أدناه أو أدخل المبالغ يدوياً.
                    </p>
                    <div v-if="item.error" class="text-xs text-red-500">{{ item.error }}</div>
                    <div v-if="item.saved" class="text-xs text-green-600 font-medium">✓ تم الحفظ</div>
                  </div>
                </div>
              </div>

              <!-- Scan / إعادة المحاولة -->
              <div v-if="items.length && (!allScanned || hasScanErrors)" class="flex flex-col items-center gap-2">
                <button
                  :disabled="scanning"
                  class="flex items-center gap-2 px-8 py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-xl font-medium transition-colors shadow-sm"
                  type="button"
                  @click="scanAll"
                >
                  <DocumentMagnifyingGlassIcon class="w-5 h-5" />
                  {{
                    scanning
                      ? 'جاري تحليل الفواتير...'
                      : hasScanErrors
                        ? 'إعادة التحليل للفاشلة'
                        : `تحليل ${itemsNeedingScan} فاتورة (OCR)`
                  }}
                </button>
                <p v-if="!scanning && !allScanned && itemsNeedingScan > 0" class="text-[11px] text-gray-500">
                  يبدأ التحليل تلقائياً بعد الرفع؛ استخدم الزر أعلاه إن توقّف أو حدث خطأ.
                </p>
              </div>

              <!-- Save All Button -->
              <div v-if="allScanned" class="flex items-center gap-3 justify-center">
                <p class="text-sm text-gray-500">تحقق من البيانات ثم احفظ</p>
                <button :disabled="saving" class="flex items-center gap-2 px-8 py-3 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white rounded-xl font-medium transition-colors shadow-sm"
                        @click="saveAll"
                >
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
import { ref, computed, watch, nextTick } from 'vue'
import { DocumentArrowUpIcon, XMarkIcon, DocumentMagnifyingGlassIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import { useToast } from '@/composables/useToast'
import { convertPdfFileToJpegFile, isPdfFile } from '@/utils/pdfToImageFile'

const emit = defineEmits<{ (e: 'saved', invoices: any[]): void }>()
const toast = useToast()

const visible   = ref(false)
const suppliers = ref<{ id: number; name: string }[]>([])
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
  /** اكتمل طلب OCR (نجاح أو خطأ من الخادم) */
  scanComplete: boolean
  saved: boolean
  error: string
  data: {
    invoice_number: string
    invoice_date: string
    total: number | null
    vat_amount: number | null
    supplier_name: string
    vat_number: string
    supplier_id: number
    line_items: Array<{
      description: string
      qty: number | null
      unit_price: number | null
      matched?: boolean
      matched_product_id?: number | null
      match_score?: number
    }>
  }
}

const items = ref<ScanItem[]>([])

const allScanned = computed(() => items.value.length > 0 && items.value.every(i => i.scanComplete))

const hasScanErrors = computed(() => items.value.some(i => i.error !== ''))

const itemsNeedingScan = computed(() => items.value.filter(i => !i.scanComplete || i.error !== '').length)

function hasUsefulExtraction(data: ScanItem['data']): boolean {
  return Boolean(
    (data.line_items?.length ?? 0) > 0
    || (data.invoice_number && String(data.invoice_number).trim() !== '')
    || (data.supplier_name && String(data.supplier_name).trim() !== '')
    || (data.total != null && Number(data.total) > 0),
  )
}

function deriveTotalFromLineItems(item: ScanItem): void {
  if (item.data.total != null && Number(item.data.total) > 0) return
  const lines = item.data.line_items
  if (!lines?.length) return
  let sum = 0
  for (const L of lines) {
    const q = Number(L.qty ?? 1) || 1
    const p = Number(L.unit_price ?? 0) || 0
    sum += q * p
  }
  if (sum > 0) {
    item.data.total = Math.round(sum * 100) / 100
  }
}

function applyOcrResultToItem(item: ScanItem, extracted: Record<string, unknown>): void {
  item.data.invoice_number = (extracted.invoice_number as string) ?? ''
  item.data.invoice_date = (extracted.invoice_date as string) ?? ''
  item.data.total = (extracted.total as number | null | undefined) ?? null
  item.data.vat_amount = (extracted.vat_amount as number | null | undefined) ?? null
  item.data.supplier_name = (extracted.supplier_name as string) ?? ''
  item.data.vat_number = (extracted.vat_number as string) ?? ''
  item.data.line_items = Array.isArray(extracted.line_items)
    ? (extracted.line_items as ScanItem['data']['line_items'])
    : []
  deriveTotalFromLineItems(item)
  if (!item.data.line_items.length && item.data.total != null) {
    item.data.line_items = [{
      description: 'بند موحّد من الفاتورة',
      qty: 1,
      unit_price: Number(item.data.total),
      matched: false,
    }]
  }
}

function makeItem(file: File): ScanItem {
  return {
    file, preview: URL.createObjectURL(file), base64: '',
    processing: false, scanComplete: false, saved: false, error: '',
    data: {
      invoice_number: '', invoice_date: '', total: null, vat_amount: null, supplier_name: '', vat_number: '',
      supplier_id: 0,
      line_items: [],
    },
  }
}

async function loadSuppliers() {
  try {
    const { data } = await apiClient.get('/suppliers', { params: { per_page: 200 } })
    const rows = data.data?.data ?? data.data ?? []
    suppliers.value = (rows as { id: number; name: string }[]).map(s => ({ id: s.id, name: s.name }))
  } catch {
    suppliers.value = []
  }
}

watch(visible, v => { if (v) loadSuppliers() })

async function toBase64(file: File): Promise<string> {
  return new Promise((res, rej) => {
    const fr = new FileReader()
    fr.onload  = () => res((fr.result as string).split(',')[1])
    fr.onerror = rej
    fr.readAsDataURL(file)
  })
}

function onFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  void handleIncomingFiles(files)
}
function onDrop(e: DragEvent) {
  dragOver.value = false
  const raw = Array.from(e.dataTransfer?.files ?? [])
  const files = raw.filter(
    f =>
      f.type.startsWith('image/')
      || f.type === 'application/pdf'
      || /\.pdf$/i.test(f.name),
  )
  void handleIncomingFiles(files)
}

async function handleIncomingFiles(files: File[]) {
  const remaining = 10 - items.value.length
  const slice = files.slice(0, remaining)
  for (const f of slice) {
    try {
      if (isPdfFile(f)) {
        const imgFile = await convertPdfFileToJpegFile(f)
        items.value.push(makeItem(imgFile))
      } else if (f.type.startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(f.name)) {
        items.value.push(makeItem(f))
      } else {
        toast.error(`صيغة غير مدعومة: ${f.name}`)
      }
    } catch {
      toast.error(`تعذّر قراءة الملف: ${f.name} (PDF تالف أو محمي؟)`)
    }
  }
  await nextTick()
  if (items.value.some(i => !i.scanComplete)) {
    await scanAll()
  }
}
function removeItem(idx: number) { items.value.splice(idx, 1) }

function onInvoiceDateChange(item: ScanItem, val: { from: string; to: string }) {
  item.data.invoice_date = val.from || val.to
}

async function scanAll() {
  scanning.value = true
  for (const item of items.value) {
    /* إعادة المحاولة: عناصر ناجحة بلا خطأ تُتخطّى؛ ما فيه خطأ يُعاد تحليله */
    if (item.scanComplete && item.error === '') continue
    item.processing = true
    item.error = ''
    try {
      item.base64 = item.base64 || await toBase64(item.file)
      const { data: json } = await apiClient.post<{ results?: Array<Record<string, unknown> & { error?: string }> }>(
        '/purchases/ocr-extract',
        { images: [item.base64], match_products: true },
      )
      const row = json.results?.[0] as Record<string, unknown> | undefined
      if (!row) {
        item.error = 'استجابة فارغة من الخادم.'
        item.scanComplete = true
        continue
      }
      if (typeof row.error === 'string' && row.error !== '') {
        item.error = row.error
        item.scanComplete = true
        continue
      }
      applyOcrResultToItem(item, row)
      item.scanComplete = true
    } catch (e: unknown) {
      const msg =
        (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      item.error = msg ? String(msg) : 'فشل الاتصال أو التحليل — تحقق من الشبكة ثم أعد المحاولة.'
      item.scanComplete = false
    } finally {
      item.processing = false
    }
  }
  scanning.value = false
}

async function saveAll() {
  saving.value    = true
  saveError.value = ''
  const saved: any[] = []
  for (const item of items.value) {
    if (item.saved) continue
    if (!item.data.supplier_id) {
      item.error = 'اختر مورداً من القائمة قبل اعتماد أمر الشراء'
      continue
    }
    const lines = item.data.line_items.length
      ? item.data.line_items
      : [{
          description: item.data.supplier_name || 'بند فاتورة',
          qty: 1,
          unit_price: Number(item.data.total ?? 0),
          matched_product_id: null as number | null,
        }]
    const poItems = lines.map((line) => {
      const qty = Number(line.qty ?? 1) || 1
      const unit = Number(line.unit_price ?? (item.data.total != null ? Number(item.data.total) / qty : 0))
      const pid = 'matched_product_id' in line && line.matched_product_id != null
        ? Number(line.matched_product_id)
        : null
      return {
        product_id: pid,
        name: line.description || 'بند',
        sku: null,
        quantity: qty,
        unit_cost: unit,
        tax_rate: 15,
      }
    })
    try {
      const { data } = await apiClient.post('/purchases', {
        supplier_id: item.data.supplier_id,
        notes: `OCR — فاتورة ${item.data.invoice_number || '—'} | ${item.data.supplier_name || ''}`.trim(),
        expected_at: item.data.invoice_date || undefined,
        items: poItems,
      })
      item.saved = true
      item.error = ''
      saved.push(data)
    } catch {
      item.error = 'فشل إنشاء أمر الشراء — راجع المورد والبنود'
    }
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
