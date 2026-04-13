<template>
  <div class="app-shell-page">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">أوامر الشراء</h2>
        <p class="page-subtitle">إدارة أوامر الشراء، حالة الاستلام، والمرفقات</p>
      </div>
      <div class="page-toolbar">
        <PurchaseInvoiceScanner @saved="onScannedSaved" />
        <RouterLink to="/purchases/new" class="btn btn-primary">+ أمر شراء جديد</RouterLink>
      </div>
    </div>

    <div class="table-toolbar">
      <select v-model="filterStatus" class="table-filter w-48" @change="load">
        <option value="">كل الحالات</option>
        <option value="pending">معلق</option>
        <option value="ordered">مطلوب</option>
        <option value="partial">مستلم جزئياً</option>
        <option value="received">مستلم</option>
        <option value="cancelled">ملغي</option>
      </select>
    </div>

    <div class="table-shell">
      <div class="panel-head">
        <span class="panel-title">قائمة أوامر الشراء</span>
        <span class="panel-muted">{{ purchases.length }} عنصر</span>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="px-4 py-3 text-right">المرجع</th>
            <th class="px-4 py-3 text-center w-10" title="مرفقات PDF">
              <span class="sr-only">مرفقات</span>
              <PaperClipIcon class="inline h-4 w-4 text-gray-400" aria-hidden="true" />
            </th>
            <th class="px-4 py-3 text-right">المورد</th>
            <th class="px-4 py-3 text-right">الإجمالي</th>
            <th class="px-4 py-3 text-right">الحالة</th>
            <th class="px-4 py-3 text-right">التاريخ المتوقع</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in purchases" :key="p.id">
            <td class="font-mono font-semibold text-sm text-gray-900 dark:text-slate-100">{{ p.reference_number }}</td>
            <td class="px-4 py-3 text-center text-sm" :title="attachmentCount(p) ? `${attachmentCount(p)} مرفق` : 'لا مرفقات'">
              <span v-if="attachmentCount(p)" class="inline-flex h-6 min-w-[1.25rem] items-center justify-center rounded-full bg-primary-100 px-1.5 text-xs font-semibold text-primary-800">
                {{ attachmentCount(p) }}
              </span>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td>{{ p.supplier?.name ?? '—' }}</td>
            <td class="font-medium">{{ Number(p.total).toFixed(2) }} ر.س</td>
            <td>
              <span :class="statusClass(p.status)" class="px-2 py-0.5 rounded-full text-xs">{{ statusLabel(p.status) }}</span>
            </td>
            <td class="text-gray-400 text-xs">{{ p.expected_at?.slice(0, 10) ?? '—' }}</td>
            <td class="px-4 py-3 text-left">
              <RouterLink :to="`/purchases/${p.id}`" class="text-primary-600 hover:underline text-xs">عرض</RouterLink>
            </td>
          </tr>
          <tr v-if="!purchases.length">
            <td colspan="7" class="table-empty">
              <p class="table-empty-title">لا توجد أوامر شراء</p>
              <p class="table-empty-sub">ابدأ بإنشاء أمر شراء جديد أو غيّر المرشح</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="table-pagination">
      <span>صفحة {{ meta.current_page }} من {{ meta.last_page }}</span>
      <div class="flex justify-end gap-2 text-sm">
        <button :disabled="meta.current_page <= 1" class="px-3 py-1 border rounded-lg disabled:opacity-40" @click="changePage(meta.current_page - 1)">السابق</button>
        <span class="py-1 px-2 text-gray-500">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" class="px-3 py-1 border rounded-lg disabled:opacity-40" @click="changePage(meta.current_page + 1)">التالي</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { PaperClipIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import PurchaseInvoiceScanner from '@/components/PurchaseInvoiceScanner.vue'

const route        = useRoute()
const purchases    = ref<any[]>([])
const filterStatus = ref('')

function onScannedSaved() { load() }
const meta         = ref<any>(null)
const page         = ref(1)

async function load() {
  const params: Record<string, any> = { page: page.value, per_page: 25 }
  if (filterStatus.value) params.status = filterStatus.value
  if (route.query.supplier_id) params.supplier_id = route.query.supplier_id
  const { data } = await apiClient.get('/purchases', { params })
  purchases.value = data.data.data ?? data.data
  meta.value = data.data.meta ?? null
}

function changePage(p: number) { page.value = p; load() }

function attachmentCount(p: any): number {
  const a = p?.document_attachments
  return Array.isArray(a) ? a.length : 0
}

function statusClass(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700', ordered: 'bg-blue-100 text-blue-700',
    partial: 'bg-orange-100 text-orange-700', received: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[s] ?? 'bg-gray-100 text-gray-500'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: 'معلق', ordered: 'مطلوب', partial: 'مستلم جزئياً', received: 'مستلم', cancelled: 'ملغي',
  }
  return m[s] ?? s
}

onMounted(load)
</script>
