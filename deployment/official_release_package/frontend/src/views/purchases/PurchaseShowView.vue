<template>
  <div class="space-y-4" dir="rtl">
    <div class="flex items-center gap-2">
      <RouterLink to="/purchases" class="text-gray-400 hover:text-gray-700 text-sm">← أوامر الشراء</RouterLink>
      <span class="text-gray-300">/</span>
      <h2 class="text-lg font-semibold text-gray-900">{{ purchase?.reference_number }}</h2>
      <span v-if="purchase" :class="statusClass(purchase.status)" class="px-2 py-0.5 rounded-full text-xs">
        {{ statusLabel(purchase.status) }}
      </span>
    </div>

    <div v-if="!purchase" class="text-gray-400 text-sm">جارٍ التحميل...</div>

    <template v-else>
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 space-y-3 text-sm">
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <h3 class="font-semibold text-gray-800 dark:text-slate-100">مرفقات PDF</h3>
          <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-900/30 text-primary-800 dark:text-primary-200 text-xs font-medium cursor-pointer hover:bg-primary-100 dark:hover:bg-primary-900/50">
            <input type="file" accept="application/pdf,.pdf" class="hidden" @change="uploadPdf" />
            رفع ملف PDF
          </label>
        </div>
        <p v-if="docUploading" class="text-xs text-primary-600">جاري الرفع...</p>
        <ul v-if="attachments.length" class="space-y-2">
          <li v-for="(a, idx) in attachments" :key="idx" class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 dark:border-slate-600 px-3 py-2">
            <a
              :href="resolveDocUrl(a)"
              target="_blank"
              rel="noopener noreferrer"
              class="min-w-0 truncate text-sm text-primary-600 hover:underline dark:text-primary-400"
            >{{ a.name }}</a>
            <button type="button" class="text-xs text-red-600 dark:text-red-400 hover:underline shrink-0" @click="removeDoc(idx)">حذف</button>
          </li>
        </ul>
        <p v-else-if="!docUploading" class="text-xs text-gray-400 dark:text-slate-500">ارفع عروض أسعار أو فواتير المورد بصيغة PDF (حتى 10 ميجا).</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 space-y-2 text-sm">
          <p><span class="text-gray-500">المورد:</span> <strong>{{ purchase.supplier?.name }}</strong></p>
          <p><span class="text-gray-500">الفرع:</span> {{ purchase.branch?.name ?? '—' }}</p>
          <p><span class="text-gray-500">تاريخ التسليم المتوقع:</span> {{ purchase.expected_at?.slice(0, 10) ?? '—' }}</p>
          <p><span class="text-gray-500">تاريخ الاستلام:</span> {{ purchase.received_at?.slice(0, 10) ?? '—' }}</p>
        </div>
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 space-y-2 text-sm">
          <p><span class="text-gray-500">المجموع الفرعي:</span> {{ Number(purchase.subtotal).toFixed(2) }} ر.س</p>
          <p><span class="text-gray-500">الضريبة:</span> {{ Number(purchase.tax_amount).toFixed(2) }} ر.س</p>
          <p class="text-base font-bold"><span class="text-gray-500 font-normal">الإجمالي:</span> {{ Number(purchase.total).toFixed(2) }} ر.س</p>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 dark:border-slate-700">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-slate-100">البنود</h3>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
              <th class="px-4 py-2 text-right">الاسم</th>
              <th class="px-4 py-2 text-right">الكمية المطلوبة</th>
              <th class="px-4 py-2 text-right">المستلم</th>
              <th class="px-4 py-2 text-right">سعر الوحدة</th>
              <th class="px-4 py-2 text-right">الإجمالي</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="item in purchase.items" :key="item.id">
              <td class="px-4 py-2 text-right">{{ item.name }} <span v-if="item.sku" class="text-gray-400 text-xs">({{ item.sku }})</span></td>
              <td class="px-4 py-2 text-right">{{ item.quantity }}</td>
              <td class="px-4 py-2 text-right">
                <span :class="item.received_quantity >= item.quantity ? 'text-green-600' : 'text-orange-500'">
                  {{ item.received_quantity }}
                </span>
              </td>
              <td class="px-4 py-2 text-right">{{ Number(item.unit_cost).toFixed(2) }} ر.س</td>
              <td class="px-4 py-2 text-right font-medium">{{ Number(item.total).toFixed(2) }} ر.س</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-2">
        <button
          v-if="canTransition('ordered')"
          class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700"
          @click="doTransition('ordered')"
        >
          تأكيد الطلب
        </button>
        <button
          v-if="canTransition('cancelled')"
          class="px-4 py-2 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
          @click="doTransition('cancelled')"
        >
          إلغاء أمر الشراء
        </button>
        <RouterLink
          v-if="canReceive"
          :to="`/purchases/${purchase.id}/receive`"
          class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
        >
          استلام البضاعة (GRN)
        </RouterLink>
      </div>

      <!-- سجل الاستلام -->
      <div v-if="receipts.length" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b text-sm font-semibold">سجل الاستلام (GRN)</div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs text-gray-500">
            <tr>
              <th class="px-4 py-2 text-right">رقم GRN</th>
              <th class="px-4 py-2 text-right">تاريخ الاستلام</th>
              <th class="px-4 py-2 text-right">الحالة</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="r in receipts" :key="r.id">
              <td class="px-4 py-2 font-mono text-right">{{ r.grn_number }}</td>
              <td class="px-4 py-2 text-gray-500 text-xs text-right">{{ r.received_at?.slice(0, 16).replace('T', ' ') }}</td>
              <td class="px-4 py-2 text-right">
                <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">مكتمل</span>
              </td>
              <td class="px-4 py-2 text-right">
                <RouterLink :to="`/goods-receipts/${r.id}`" class="text-primary-600 hover:underline text-xs">عرض</RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useToast } from '@/composables/useToast'
import { appConfirm } from '@/services/appConfirmDialog'
import { resolveStoragePublicUrl } from '@/utils/storageUrl'

const route         = useRoute()
const toast         = useToast()
const purchase      = ref<any>(null)
const receipts      = ref<any[]>([])
const docUploading  = ref(false)

const attachments = computed(() => purchase.value?.document_attachments ?? [])

function resolveDocUrl(a: { url?: string }) {
  return resolveStoragePublicUrl(a?.url)
}

async function uploadPdf(e: Event) {
  const input = e.target as HTMLInputElement
  const file  = input.files?.[0]
  if (!file || !purchase.value) return
  if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
    toast.warning('صيغة غير مدعومة', 'يرجى اختيار ملف PDF فقط (حتى 10 ميجا).')
    input.value = ''
    return
  }
  docUploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', file)
    await apiClient.post(`/purchases/${purchase.value.id}/documents`, fd)
    toast.success('تم الرفع', 'أضيف المستند إلى أمر الشراء.')
    await load()
  } catch (e: any) {
    toast.error('فشل الرفع', e?.response?.data?.message ?? 'تحقق من الصلاحيات وحجم الملف.')
  } finally {
    docUploading.value = false
    input.value        = ''
  }
}

async function removeDoc(idx: number) {
  if (!purchase.value) return
  const ok = await appConfirm({
    title: 'حذف المرفق',
    message: 'حذف هذا الملف؟',
    variant: 'danger',
    confirmLabel: 'حذف',
  })
  if (!ok) return
  try {
    await apiClient.delete(`/purchases/${purchase.value.id}/documents/${idx}`)
    toast.success('تم الحذف')
    await load()
  } catch {
    toast.error('تعذر حذف المرفق')
  }
}

const TRANSITIONS: Record<string, string[]> = {
  pending:   ['ordered', 'cancelled'],
  ordered:   ['partial', 'received', 'cancelled'],
  partial:   ['received', 'cancelled'],
  received:  [],
  cancelled: [],
}

const canReceive = computed(() => {
  const s = purchase.value?.status
  return s && ['pending', 'ordered', 'partial'].includes(s)
})

function canTransition(target: string): boolean {
  const s = purchase.value?.status
  return s ? (TRANSITIONS[s] ?? []).includes(target) : false
}

async function load() {
  const id = route.params.id
  const [po, gr] = await Promise.all([
    apiClient.get(`/purchases/${id}`),
    apiClient.get(`/purchases/${id}/receipts`),
  ])
  purchase.value = po.data.data
  receipts.value = gr.data.data ?? []
}

async function doTransition(status: string) {
  await apiClient.patch(`/purchases/${purchase.value.id}/status`, { status })
  await load()
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: 'معلق', ordered: 'مطلوب', partial: 'مستلم جزئياً',
    received: 'مستلم', cancelled: 'ملغي',
  }
  return m[s] ?? s
}

function statusClass(s: string): string {
  const m: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    ordered:   'bg-blue-100 text-blue-700',
    partial:   'bg-orange-100 text-orange-700',
    received:  'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[s] ?? 'bg-gray-100 text-gray-500'
}

onMounted(load)
</script>
