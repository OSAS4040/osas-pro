<template>
  <section id="admin-section-audit" class="scroll-mt-32 mb-16">
    <div class="mb-4 border-b border-slate-200 pb-3 dark:border-slate-700">
      <h2 class="platform-admin-section-heading">تدقيق المنصة</h2>
      <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600 dark:text-slate-400">
        سجل إجراءات إدارية على مستوى المنصة؛ اضغط «تحميل السجلات» لجلب آخر البيانات من الخادم.
      </p>
      <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">
        للسياق والصلاحيات:
        <RouterLink
          :to="{ name: 'platform-governance' }"
          class="font-bold text-primary-700 underline underline-offset-2 hover:text-primary-900 dark:text-primary-400"
        >الحوكمة والسياق</RouterLink>
      </p>
    </div>
    <div class="flex flex-wrap gap-3 mb-4">
      <input
        :value="auditCompanyFilter"
        type="number"
        min="1"
        placeholder="تصفية برقم الشركة (اختياري)"
        class="min-w-48 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
        @input="onCompanyFilterInput"
      />
      <button
        type="button"
        class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-800 transition-colors hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
        @click="emit('load-audit')"
      >
        تحميل السجلات
      </button>
    </div>
    <div v-if="auditLoading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white py-4 dark:border-slate-800 dark:bg-slate-900" aria-busy="true">
      <p class="sr-only">جاري تحميل السجلات</p>
      <table class="w-full min-w-[640px] text-sm">
        <thead class="bg-slate-100 dark:bg-slate-800/50">
          <tr>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">#</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجراء</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الشركة</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستخدم</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الوقت</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
          <tr v-for="sk in 6" :key="'au-sk-' + sk">
            <td v-for="col in 5" :key="'au-sk-' + sk + '-' + col" class="px-3 py-2.5">
              <div class="h-3.5 animate-pulse rounded bg-slate-200 dark:bg-slate-700" :class="col === 3 ? 'w-[70%]' : 'w-[45%]'" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div
      v-else-if="auditError"
      class="rounded-xl border border-red-200 bg-red-50 px-4 py-6 text-center text-sm text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100"
    >
      تعذر تحميل سجلات التدقيق — تحقق من الشبكة وصلاحية قراءة تدقيق المنصة، ثم اضغط «تحميل السجلات» مرة أخرى.
    </div>
    <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-slate-200 dark:border-gray-800 shadow-sm dark:shadow-none overflow-x-auto">
      <table class="w-full text-sm min-w-[640px]">
        <thead class="bg-slate-100 dark:bg-slate-800/50">
          <tr>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">#</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجراء</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الشركة</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستخدم</th>
            <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الوقت</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
          <tr v-for="row in auditRows" :key="row.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
            <td class="px-3 py-2 font-mono text-sm text-slate-500 dark:text-slate-400">{{ row.id }}</td>
            <td class="px-3 py-2 text-sm text-slate-800 dark:text-slate-200" :title="row.action">{{ formatAuditAction(row.action) }}</td>
            <td class="px-3 py-2 text-sm text-slate-700 dark:text-slate-300">{{ row.company_id != null ? `شركة رقم ${row.company_id}` : '—' }}</td>
            <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ formatAuditUserId(row.user_id) }}</td>
            <td class="px-3 py-2 text-xs text-slate-500 dark:text-slate-400">{{ row.created_at }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!auditLoading && auditRows.length === 0" class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">لا توجد سجلات مطابقة. جرّب «تحميل السجلات» أو غيّر تصفية رقم الشركة.</p>
    </div>
  </section>
</template>

<script setup lang="ts">
/**
 * Phase 3 — Step 6: عرض قسم التدقيق فقط.
 * التحميل و`loadAuditLogs` تبقى في الصفحة الأم.
 */
import { RouterLink } from 'vue-router'
import { formatAuditAction, formatAuditUserId } from '@/utils/governanceAuditLabels'

defineProps<{
  auditCompanyFilter: string
  auditLoading: boolean
  auditError: boolean
  /** نفس المصفوفة المرجعة من الصفحة دون إعادة تشكيل. */
  auditRows: any[]
}>()

const emit = defineEmits<{
  'update:auditCompanyFilter': [value: string]
  'load-audit': []
}>()

function onCompanyFilterInput(e: Event): void {
  const el = e.target as HTMLInputElement
  emit('update:auditCompanyFilter', el.value)
}
</script>
