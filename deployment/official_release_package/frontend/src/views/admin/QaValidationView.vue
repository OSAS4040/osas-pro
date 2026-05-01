<template>
  <div class="mx-auto max-w-5xl space-y-6 pb-12" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50/80 p-3 dark:border-slate-600 dark:bg-slate-800/40">
      <div class="flex flex-wrap items-center gap-2">
        <RouterLink
          to="/platform/overview"
          class="inline-flex items-center gap-1 rounded-lg border border-primary-200 bg-primary-50/90 px-3 py-1.5 text-xs font-semibold text-primary-900 hover:bg-primary-100/90 dark:border-primary-800/60 dark:bg-primary-950/35 dark:text-primary-100 dark:hover:bg-primary-900/40"
        >
          <ArrowRightIcon class="h-3.5 w-3.5 opacity-70" />
          رجوع للوحة المنصة
        </RouterLink>
        <RouterLink
          to="/"
          class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
        >
          العودة للتطبيق
        </RouterLink>
      </div>
      <div class="flex items-center gap-0.5 rounded-lg border border-gray-200 bg-white p-0.5 dark:border-slate-600 dark:bg-slate-900/60" role="group" aria-label="مظهر العرض">
        <button
          type="button"
          class="rounded-md p-1.5"
          :class="!darkMode.isDark.value && darkMode.themeMode.value === 'manual' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800'"
          title="نهاري"
          @click="darkMode.setLight()"
        >
          <SunIcon class="h-4 w-4" />
        </button>
        <button
          type="button"
          class="rounded-md p-1.5"
          :class="darkMode.isDark.value && darkMode.themeMode.value === 'manual' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800'"
          title="ليلي"
          @click="darkMode.setDark()"
        >
          <MoonIcon class="h-4 w-4" />
        </button>
        <button
          type="button"
          class="rounded-md px-2 py-1 text-[10px] font-bold"
          :class="darkMode.themeMode.value === 'auto' ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800'"
          title="تلقائي حسب الوقت"
          @click="darkMode.setAuto()"
        >
          تلقائي
        </button>
      </div>
    </div>
    <div>
      <h1 class="text-xl font-bold text-gray-900 dark:text-white">التحقق من النظام (QA)</h1>
      <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
        تشغيل اختبارات الضغط (1000 عملية DB) وسباق idempotency على المحفظة — للمالك فقط. النتائج تُحفظ في الخادم وتُعرض هنا.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <button
        type="button"
        :disabled="running"
        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-md"
        @click="runTests"
      >
        <ArrowPathIcon class="w-5 h-5" :class="{ 'animate-spin': running }" />
        تشغيل الاختبارات الآن
      </button>
      <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-slate-300 cursor-pointer">
        <input v-model="includeSimulation" type="checkbox" class="rounded border-gray-300" />
        تضمين simulation:stress (أثقل — قد يستغرق دقائق)
      </label>
    </div>

    <p v-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>
    <p v-if="running" class="text-sm text-amber-600">جاري التنفيذ — قد يستغرق حتى 5 دقائق…</p>

    <div v-if="data" class="space-y-4">
      <div class="grid sm:grid-cols-2 gap-3">
        <div class="card p-4 border border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800">
          <p class="text-xs text-gray-500">آخر تشغيل</p>
          <p class="font-mono text-sm font-semibold">{{ data.generated_at }}</p>
        </div>
        <div class="card p-4 border border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800">
          <p class="text-xs text-gray-500">حالة النظام</p>
          <p class="text-2xl font-bold" :class="statusClass">{{ data.system_status }}</p>
        </div>
      </div>

      <div class="card p-4 border border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800">
        <h2 class="text-sm font-bold text-gray-800 dark:text-white mb-2">Stress (DB ping)</h2>
        <ul class="text-sm space-y-1 text-gray-700 dark:text-slate-300">
          <li>عدد العمليات: <strong>{{ data.stress_db_ping?.operations }}</strong></li>
          <li>نجاح: <strong>{{ data.stress_db_ping?.success_count }}</strong> — فشل: <strong>{{ data.stress_db_ping?.failure_count }}</strong></li>
          <li>متوسط الوقت (ms): <strong>{{ data.stress_db_ping?.latency_ms?.avg }}</strong></li>
          <li>min / max / p95 (ms): <strong>{{ data.stress_db_ping?.latency_ms?.min }}</strong> / <strong>{{ data.stress_db_ping?.latency_ms?.max }}</strong> / <strong>{{ data.stress_db_ping?.latency_ms?.p95 }}</strong></li>
        </ul>
      </div>

      <div class="card p-4 border border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800">
        <h2 class="text-sm font-bold text-gray-800 dark:text-white mb-2">Race (محفظة — نفس مفتاح idempotency)</h2>
        <ul class="text-sm space-y-1 text-gray-700 dark:text-slate-300">
          <li>العمال المتزامنون: <strong>{{ data.wallet_race?.workers }}</strong></li>
          <li>نجاح (exit 0): <strong>{{ data.wallet_race?.exit_code_0_success }}</strong></li>
          <li>مكرر (exit 2): <strong>{{ data.wallet_race?.exit_code_2_duplicate }}</strong></li>
          <li>أخطاء أخرى: <strong>{{ data.wallet_race?.exit_other_failure }}</strong></li>
          <li class="font-mono text-xs break-all">مفتاح: {{ data.wallet_race?.shared_idempotency_key_prefix }}</li>
        </ul>
      </div>

      <div class="card p-4 border border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800">
        <h2 class="text-sm font-bold text-gray-800 dark:text-white mb-2">سلامة (استنتاج من الاختبارات)</h2>
        <ul class="text-sm space-y-1">
          <li>اشتباه double debit (سباق &gt;1 نجاح): <strong>{{ data.integrity_flags?.double_debit_suspected ? 'نعم ⚠️' : 'لا' }}</strong></li>
          <li>فاتورة مكررة (غير مفحوص): <strong>{{ data.integrity_flags?.duplicate_invoice_suspected ? 'نعم' : 'لا' }}</strong></li>
          <li>مخزون سالب (غير مفحوص): <strong>{{ data.integrity_flags?.negative_stock_suspected ? 'نعم' : 'لا' }}</strong></li>
        </ul>
        <p class="text-xs text-gray-400 mt-2">{{ data.integrity_flags?.note }}</p>
      </div>

      <div v-if="data.simulation_stress?.ran" class="card p-4 border border-gray-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800">
        <h2 class="text-sm font-bold text-gray-800 dark:text-white mb-2">simulation:stress</h2>
        <p class="text-sm">exit: <strong>{{ data.simulation_stress?.exit_code }}</strong></p>
        <pre class="text-xs bg-gray-50 dark:bg-slate-800 p-2 rounded mt-2 overflow-auto max-h-40">{{ data.simulation_stress?.stdout_tail }}</pre>
      </div>

      <details class="text-sm">
        <summary class="cursor-pointer text-primary-600">عرض JSON كامل</summary>
        <pre class="mt-2 p-3 bg-slate-900 text-green-400 rounded text-xs overflow-auto max-h-96">{{ jsonPretty }}</pre>
      </details>
    </div>

    <p v-else-if="!running && !error" class="text-sm text-gray-500">
      لا توجد نتيجة محفوظة بعد — اضغط «تشغيل الاختبارات الآن» أو تأكد أنك مسجّل كمالك.
    </p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowPathIcon, ArrowRightIcon, SunIcon, MoonIcon } from '@heroicons/vue/24/outline'
import apiClient from '@/lib/apiClient'
import { useDarkMode } from '@/composables/useDarkMode'

const darkMode = useDarkMode()

/** شكل استجابة /internal/test-results و run-tests */
interface QaValidationPayload {
  generated_at?: string
  system_status?: string
  stress_db_ping?: {
    operations?: number
    success_count?: number
    failure_count?: number
    latency_ms?: { avg?: number; min?: number; max?: number; p95?: number }
  }
  wallet_race?: {
    workers?: number
    exit_code_0_success?: number
    exit_code_2_duplicate?: number
    exit_other_failure?: number
    shared_idempotency_key_prefix?: string
  }
  integrity_flags?: {
    double_debit_suspected?: boolean
    duplicate_invoice_suspected?: boolean
    negative_stock_suspected?: boolean
    note?: string
  }
  simulation_stress?: {
    ran?: boolean
    exit_code?: number
    stdout_tail?: string
  }
}

const running = ref(false)
const error = ref('')
const data = ref<QaValidationPayload | null>(null)
const includeSimulation = ref(false)

const statusClass = computed(() => {
  const s = data.value?.system_status as string | undefined
  if (s === 'PASS') return 'text-emerald-600 dark:text-emerald-400'
  if (s === 'FAIL') return 'text-red-600 dark:text-red-400'
  return 'text-amber-600 dark:text-amber-400'
})

const jsonPretty = computed(() => JSON.stringify(data.value, null, 2))

async function loadResults() {
  error.value = ''
  try {
    const res = await apiClient.get('/internal/test-results')
    data.value = (res.data?.data ?? null) as QaValidationPayload | null
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string } } }
    error.value = ax.response?.data?.message ?? (e instanceof Error ? e.message : 'فشل تحميل النتائج')
  }
}

async function runTests() {
  running.value = true
  error.value = ''
  try {
    const res = await apiClient.post(
      '/internal/run-tests',
      {
        stress_ops: 1000,
        race_workers: 20,
        include_simulation: includeSimulation.value,
      },
      { timeout: 300_000 },
    )
    data.value = (res.data?.data ?? null) as QaValidationPayload | null
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string } } }
    error.value = ax.response?.data?.message ?? (e instanceof Error ? e.message : 'فشل التشغيل')
    await loadResults()
  } finally {
    running.value = false
  }
}

onMounted(() => {
  loadResults()
})
</script>
