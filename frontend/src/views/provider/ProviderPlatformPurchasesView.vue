<template>
  <div class="app-shell-page">
    <div class="page-head">
      <div class="page-title-wrap">
        <h2 class="page-title-xl">{{ l('مشتريات المنصّة (تسوية للمزوّد)', 'Platform purchases (provider settlement)') }}</h2>
        <p class="page-subtitle">
          {{ l('فواتير/أوامر شراء صادرة من المنصّة باتجاه مزوّد الخدمة.', 'Purchase records issued by the platform to the service provider.') }}
        </p>
      </div>
    </div>

    <div class="table-toolbar">
      <select v-model="filterStatus" class="table-filter w-48" @change="load">
        <option value="">{{ l('كل الحالات', 'All statuses') }}</option>
        <option value="pending">{{ l('معلق', 'Pending') }}</option>
        <option value="ordered">{{ l('مطلوب', 'Ordered') }}</option>
        <option value="partial">{{ l('مستلم جزئياً', 'Partial') }}</option>
        <option value="received">{{ l('مستلم', 'Received') }}</option>
        <option value="cancelled">{{ l('ملغي', 'Cancelled') }}</option>
      </select>
    </div>

    <div class="table-shell">
      <div class="panel-head">
        <span class="panel-title">{{ l('قائمة مشتريات المنصّة', 'Platform purchase list') }}</span>
        <span class="panel-muted">{{ purchases.length }} {{ l('عنصر', 'items') }}</span>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="px-4 py-3 text-right">{{ l('المرجع', 'Reference') }}</th>
            <th class="px-4 py-3 text-right">{{ l('المورد', 'Supplier') }}</th>
            <th class="px-4 py-3 text-right">{{ l('الإجمالي', 'Total') }}</th>
            <th class="px-4 py-3 text-right">{{ l('الحالة', 'Status') }}</th>
            <th class="px-4 py-3 text-right">{{ l('التاريخ المتوقع', 'Expected') }}</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in purchases" :key="p.id">
            <td class="font-mono font-semibold text-sm text-gray-900 dark:text-slate-100">{{ p.reference_number }}</td>
            <td>{{ p.supplier?.name ?? '—' }}</td>
            <td class="font-medium">{{ Number(p.total).toFixed(2) }} {{ l('ر.س', 'SAR') }}</td>
            <td>
              <span :class="statusClass(p.status)" class="rounded-full px-2 py-0.5 text-xs">{{ statusLabel(p.status) }}</span>
            </td>
            <td class="text-xs text-gray-400">{{ p.expected_at?.slice(0, 10) ?? '—' }}</td>
            <td class="px-4 py-3 text-left">
              <RouterLink :to="`/purchases/${p.id}`" class="text-xs text-primary-600 hover:underline">{{ l('عرض', 'View') }}</RouterLink>
            </td>
          </tr>
          <tr v-if="!purchases.length">
            <td colspan="6" class="table-empty">
              <p class="table-empty-title">{{ l('لا توجد مشتريات من المنصّة', 'No platform purchases') }}</p>
              <p class="table-empty-sub">{{ l('تظهر هنا أوامر التسوية عند إصدارها من المنصّة.', 'Settlement purchase orders appear here when issued by the platform.') }}</p>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="table-pagination">
      <span>{{ l('صفحة', 'Page') }} {{ meta.current_page }} {{ l('من', 'of') }} {{ meta.last_page }}</span>
      <div class="flex justify-end gap-2 text-sm">
        <button :disabled="meta.current_page <= 1" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page - 1)">{{ l('السابق', 'Previous') }}</button>
        <span class="px-2 py-1 text-gray-500">{{ meta.current_page }} / {{ meta.last_page }}</span>
        <button :disabled="meta.current_page >= meta.last_page" class="rounded-lg border px-3 py-1 disabled:opacity-40" @click="changePage(meta.current_page + 1)">{{ l('التالي', 'Next') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { useLocale } from '@/composables/useLocale'

const locale = useLocale()
const l = (ar: string, en: string) => (locale.lang.value === 'ar' ? ar : en)

const purchases = ref<any[]>([])
const filterStatus = ref('')
const meta = ref<any>(null)
const page = ref(1)

async function load() {
  const params: Record<string, any> = {
    page: page.value,
    per_page: 25,
    platform_settlement: 1,
  }
  if (filterStatus.value) params.status = filterStatus.value
  const { data } = await apiClient.get('/purchases', { params })
  const root = data?.data
  purchases.value = Array.isArray(root?.data) ? root.data : []
  meta.value = root && 'current_page' in root
    ? { current_page: root.current_page, last_page: root.last_page }
    : null
}

function changePage(p: number) {
  page.value = p
  load()
}

function statusClass(s: string): string {
  const m: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    ordered: 'bg-blue-100 text-blue-700',
    partial: 'bg-orange-100 text-orange-700',
    received: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-600',
  }
  return m[String(s)] ?? 'bg-gray-100 text-gray-700'
}

function statusLabel(s: string): string {
  const m: Record<string, string> = {
    pending: l('معلق', 'Pending'),
    ordered: l('مطلوب', 'Ordered'),
    partial: l('مستلم جزئياً', 'Partial'),
    received: l('مستلم', 'Received'),
    cancelled: l('ملغي', 'Cancelled'),
  }
  return m[String(s)] ?? String(s)
}

onMounted(() => load())
</script>
