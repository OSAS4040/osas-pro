<template>
  <div class="app-shell-page" dir="rtl">
    <div class="flex items-center gap-2">
      <RouterLink to="/purchases" class="text-gray-400 hover:text-gray-700 text-sm">← أوامر الشراء</RouterLink>
      <span class="text-gray-300">/</span>
      <h2 class="text-lg font-semibold text-gray-900">أمر شراء جديد</h2>
    </div>

    <form class="form-shell" @submit.prevent="submit">
      <p v-if="suppliersLoadError" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ suppliersLoadError }}</p>
      <p class="rounded-xl border border-dashed border-primary-200 bg-primary-50/60 px-4 py-3 text-xs text-primary-900 leading-relaxed">
        بعد حفظ أمر الشراء يمكنك من صفحة التفاصيل رفع ملفات
        <span class="font-semibold">PDF</span>
        (عروض أسعار، فواتير مورد، إلخ) حتى 10 ميجا لكل ملف.
      </p>
      <section class="form-section">
        <h3 class="form-section-title">بيانات أمر الشراء</h3>
        <div class="form-grid-2">
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">المورد <span class="text-red-500">*</span></label>
            <select v-model="form.supplier_id" class="field" required>
              <option value="">اختر موردًا...</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">تاريخ التسليم المتوقع</label>
            <SmartDatePicker :model-value="form.expected_at" mode="single" @change="onExpectedDateChange" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-600 mb-1">ملاحظات</label>
            <textarea v-model="form.notes" class="form-textarea" rows="2" />
          </div>
        </div>
      </section>

      <section class="form-section">
        <div class="flex items-center justify-between mb-2 flex-wrap gap-2">
          <h3 class="form-section-title w-full !border-0 !pb-0 sm:w-auto flex-1">البنود</h3>
          <div class="flex items-center gap-2 flex-wrap">
            <input
              ref="ocrFileInput"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/jpg"
              class="sr-only"
              @change="onOcrFileChange"
            />
            <button
              v-if="auth.hasPermission('purchases.create')"
              type="button"
              :disabled="ocrLoading"
              class="text-sm text-teal-700 hover:underline disabled:opacity-50"
              title="رفع صورة فاتورة مورد — راجع المبالغ قبل الحفظ"
              @click="openOcrPicker"
            >
              {{ ocrLoading ? 'جارٍ الاستخراج…' : '📷 استخراج من صورة' }}
            </button>
            <PurchaseInvoiceScanner
              v-if="auth.hasPermission('purchases.create')"
              @saved="onPurchaseScannerSaved"
            />
            <button type="button" class="text-sm text-primary-600 hover:underline" @click="addItem">+ إضافة بند</button>
          </div>
        </div>
        <p v-if="ocrHint" class="text-[11px] text-teal-800 bg-teal-50 rounded-lg px-2 py-1.5 mb-2">{{ ocrHint }}</p>
        <table class="data-table data-table-wrap">
          <thead>
            <tr>
              <th class="px-3 py-2 text-right">الاسم</th>
              <th class="px-3 py-2 text-right">الرمز (SKU)</th>
              <th class="px-3 py-2 text-right">الكمية</th>
              <th class="px-3 py-2 text-right">سعر الوحدة</th>
              <th class="px-3 py-2 text-right">الضريبة %</th>
              <th class="px-3 py-2 text-right">الإجمالي</th>
              <th class="px-3 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in form.items" :key="idx">
              <td class="px-3 py-2">
                <input v-model="item.name" type="text" class="field-sm" required placeholder="اسم البند" />
              </td>
              <td class="px-3 py-2">
                <input v-model="item.sku" type="text" class="field-sm w-28" placeholder="اختياري" />
              </td>
              <td class="px-3 py-2">
                <input v-model.number="item.quantity" type="number" min="0.001" step="0.001" class="field-sm w-24 text-center" required />
              </td>
              <td class="px-3 py-2">
                <input v-model.number="item.unit_cost" type="number" min="0" step="0.01" class="field-sm w-28 text-center" required />
              </td>
              <td class="px-3 py-2">
                <input v-model.number="item.tax_rate" type="number" min="0" max="100" class="field-sm w-20 text-center" />
              </td>
              <td class="px-3 py-2 text-center font-medium">
                {{ lineTotal(item).toFixed(2) }} ر.س
              </td>
              <td class="px-3 py-2">
                <button type="button" class="text-red-400 hover:text-red-600" @click="removeItem(idx)">✕</button>
              </td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 border-t">
            <tr>
              <td colspan="5" class="px-3 py-2 text-right text-sm font-semibold">الإجمالي</td>
              <td class="px-3 py-2 text-center font-bold">{{ grandTotal.toFixed(2) }} ر.س</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </section>

      <div v-if="error" class="text-red-500 text-sm bg-red-50 rounded p-2">{{ error }}</div>
      <div class="form-actions">
        <RouterLink to="/purchases" class="btn btn-outline">إلغاء</RouterLink>
        <button type="submit" class="btn btn-primary disabled:opacity-50" :disabled="saving || !form.items.length">
          {{ saving ? 'جارٍ الحفظ...' : 'إنشاء أمر الشراء' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import apiClient from '@/lib/apiClient'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'
import PurchaseInvoiceScanner from '@/components/PurchaseInvoiceScanner.vue'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { summarizeAxiosError } from '@/utils/apiErrorSummary'

const router   = useRouter()
const auth = useAuthStore()
const toast = useToast()
const suppliers = ref<any[]>([])
const saving   = ref(false)
const error    = ref('')
const suppliersLoadError = ref('')
const ocrFileInput = ref<HTMLInputElement | null>(null)
const ocrLoading = ref(false)
const ocrHint = ref('')

const form = ref({
  supplier_id: '',
  expected_at: '',
  notes: '',
  items: [] as any[],
})

type OcrLineItem = {
  description?: string
  qty?: number | null
  unit_price?: number | null
  matched_product_id?: number | null
}

type PoLine = { name: string; sku: string; quantity: number; unit_cost: number; tax_rate: number; product_id?: number | null }

function openOcrPicker() {
  ocrHint.value = ''
  ocrFileInput.value?.click()
}

function fileToBase64Payload(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const r = new FileReader()
    r.onload = () => {
      const s = String(r.result ?? '')
      const comma = s.indexOf(',')
      resolve(comma >= 0 ? s.slice(comma + 1) : s)
    }
    r.onerror = () => reject(r.error ?? new Error('read failed'))
    r.readAsDataURL(file)
  })
}

async function onOcrFileChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !auth.hasPermission('purchases.create')) return
  ocrLoading.value = true
  ocrHint.value = ''
  try {
    const b64 = await fileToBase64Payload(file)
    const { data } = await apiClient.post<{ results?: Array<{ success?: boolean; error?: string; line_items?: OcrLineItem[] }> }>(
      '/purchases/ocr-extract',
      { images: [b64], match_products: true },
      { skipGlobalErrorToast: true },
    )
    const results = Array.isArray(data?.results) ? data.results : []
    const first = results.find((r) => r?.success && Array.isArray(r.line_items) && r.line_items.length)
    if (!first?.line_items?.length) {
      const err = results.find((r) => r?.error)?.error
      toast.error(err ? String(err) : 'لم يُستخرج أي بند من الصورة.')
      return
    }
    const mapped: PoLine[] = first.line_items.map((li) => ({
      name: (li.description && String(li.description).trim()) || 'بند',
      sku: '',
      quantity: li.qty != null && Number.isFinite(Number(li.qty)) ? Math.max(0.001, Number(li.qty)) : 1,
      unit_cost: li.unit_price != null && Number.isFinite(Number(li.unit_price)) ? Number(li.unit_price) : 0,
      tax_rate: 15,
      product_id:
        li.matched_product_id != null && Number.isFinite(Number(li.matched_product_id))
          ? Number(li.matched_product_id)
          : null,
    }))
    for (const row of mapped) {
      const { product_id: pid, ...rest } = row
      const line: Record<string, unknown> = { ...rest }
      if (pid != null) line.product_id = pid
      form.value.items.push(line as PoLine)
    }
    ocrHint.value = `أُضيف ${mapped.length} بنداً من OCR — راجع التكاليف والكمية واختيار المورد.`
    toast.success('تم استخراج البنود')
  } catch (e: unknown) {
    toast.error(summarizeAxiosError(e))
  } finally {
    ocrLoading.value = false
  }
}

/** بعد حفظ أوامر من نافذة الماسح الضوئي: توجيه لأول أمر. */
function onPurchaseScannerSaved(saved: unknown) {
  const list = Array.isArray(saved) ? saved : []
  const first = list[0] as { data?: { id?: number } } | undefined
  const id = first?.data?.id
  if (typeof id === 'number') {
    router.push(`/purchases/${id}`)
  }
}

onMounted(async () => {
  suppliersLoadError.value = ''
  try {
    const { data } = await apiClient.get('/suppliers', {
      params: { is_active: true, per_page: 200 },
      skipGlobalErrorToast: true,
    })
    suppliers.value = data.data.data ?? data.data
  } catch (e: unknown) {
    suppliersLoadError.value = summarizeAxiosError(e)
  }
  addItem()
})

function addItem() {
  form.value.items.push({ name: '', sku: '', quantity: 1, unit_cost: 0, tax_rate: 15 })
}

function removeItem(idx: number) {
  form.value.items.splice(idx, 1)
}

function lineTotal(item: any): number {
  const base = item.quantity * item.unit_cost
  return base + base * ((item.tax_rate ?? 15) / 100)
}

const grandTotal = computed(() => form.value.items.reduce((s, i) => s + lineTotal(i), 0))

function onExpectedDateChange(val: { from: string; to: string }) {
  form.value.expected_at = val.from || val.to
}

async function submit() {
  if (saving.value) return
  saving.value = true
  error.value = ''
  try {
    const { data } = await apiClient.post('/purchases', form.value, { skipGlobalErrorToast: true })
    router.push(`/purchases/${data.data.id}`)
  } catch (e: unknown) {
    error.value = summarizeAxiosError(e)
  } finally {
    saving.value = false
  }
}
</script>
