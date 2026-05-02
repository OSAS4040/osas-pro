<template>
  <div class="mx-auto max-w-[1200px] space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">تقارير المنصة — نبض تشغيلي</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">ملخص من مسار التقارير المعتمد للمشغّلين (صلاحية التقارير).</p>
      </div>
      <RouterLink :to="{ name: 'platform-overview' }" class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-400">← الملخص</RouterLink>
    </div>

    <div v-if="!auth.hasPermission('platform.reporting.read')" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
      لا تملك صلاحية قراءة تقارير المنصة.
    </div>

    <template v-else>
      <div v-if="loading" class="text-slate-400">جارٍ التحميل…</div>
      <div v-else-if="errorMsg" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">{{ errorMsg }}</div>
      <pre
        v-else-if="payload"
        class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
        dir="ltr"
      >{{ JSON.stringify(payload, null, 2) }}</pre>
    </template>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import apiClient from '@/lib/apiClient'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const loading = ref(false)
const payload = ref<unknown>(null)
const errorMsg = ref<string | null>(null)

async function load(): Promise<void> {
  if (!auth.hasPermission('platform.reporting.read')) return
  loading.value = true
  errorMsg.value = null
  payload.value = null
  try {
    const end = new Date()
    const start = new Date()
    start.setDate(start.getDate() - 30)
    const from = start.toISOString().slice(0, 10)
    const to = end.toISOString().slice(0, 10)
    const { data } = await apiClient.get('/reporting/v1/platform/pulse-summary', { params: { from, to } })
    payload.value = data
  } catch {
    errorMsg.value = 'تعذّر تحميل التقرير — تحقق من الصلاحيات أو الشبكة.'
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
</script>
