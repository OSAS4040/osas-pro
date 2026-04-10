<template>
  <div class="app-shell-page max-w-2xl space-y-6" dir="rtl">
    <div>
      <h1 class="page-title-xl">بيانات النشر (Deployment Proof)</h1>
      <p class="page-subtitle text-sm mt-1">
        القيم التالية تُحقَن وقت <code class="text-xs bg-gray-100 dark:bg-slate-800 px-1 rounded">vite build</code> — ليست وقت التشغيل في المتصفح.
      </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 space-y-3">
      <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100">الواجهة (المضمّن في dist)</h2>
      <dl class="grid gap-2 text-sm">
        <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-slate-700 pb-2">
          <dt class="text-gray-500">{{ labels.version }}</dt>
          <dd class="font-mono font-medium">{{ buildInfo.version }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-slate-700 pb-2">
          <dt class="text-gray-500">{{ labels.buildTime }}</dt>
          <dd class="font-mono text-xs break-all">{{ buildInfo.buildTime }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-slate-700 pb-2">
          <dt class="text-gray-500">{{ labels.commit }}</dt>
          <dd class="font-mono">{{ buildInfo.commit }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-b border-gray-100 dark:border-slate-700 pb-2">
          <dt class="text-gray-500">{{ labels.branch }}</dt>
          <dd class="font-mono">{{ buildInfo.branch }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-gray-500">{{ labels.environment }}</dt>
          <dd class="font-mono">{{ buildInfo.environment }}</dd>
        </div>
      </dl>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 space-y-3">
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100">الخادم · GET /api/v1/system/version</h2>
        <button type="button" class="text-xs font-semibold text-primary-600" @click="loadBackend">
          تحديث
        </button>
      </div>
      <p v-if="loading" class="text-sm text-gray-500">جارٍ الجلب…</p>
      <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>
      <template v-else-if="backend">
        <dl class="grid gap-2 text-sm">
          <div v-for="k in backendKeys" :key="k"
               class="flex justify-between gap-4 border-b border-gray-100 dark:border-slate-700 pb-2 last:border-0"
          >
            <dt class="text-gray-500">{{ labelsByBackendKey[k] }}</dt>
            <dd class="font-mono text-xs break-all text-left dir-ltr">{{ displayVal(backend[k]) }}</dd>
          </div>
        </dl>
        <p v-if="parityHint" class="text-xs font-medium rounded-lg p-3" :class="parityOk ? 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-amber-50 text-amber-900 dark:bg-amber-950/35 dark:text-amber-100'">
          {{ parityHint }}
        </p>
      </template>
      <p v-else class="text-sm text-gray-500">اضغط «تحديث» لمقارنة الخادم مع الواجهة.</p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
      <h2 class="text-sm font-bold text-gray-800 dark:text-slate-100 mb-2">مسرد المنصة والمستأجر</h2>
      <p class="text-sm text-gray-600 dark:text-slate-300 mb-3">
        تعريفات ثابتة: منصة أسس برو، الشركة المشتركة، العميل النهائي، والبوابات — لتقليل الخلط في الدعم والتدريب.
      </p>
      <RouterLink
        to="/about/taxonomy"
        class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline"
      >
        فتح مسرد الكيانات والبوابات
      </RouterLink>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'
import { buildInfo } from '@/config/appRelease'
import { useLocale } from '@/composables/useLocale'

const loading = ref(false)
const error   = ref('')
const backend = ref<Record<string, string | null> | null>(null)
const locale = useLocale()

const labels = computed(() => {
  const ar = locale.lang.value === 'ar'
  return {
    version: ar ? 'الإصدار' : 'Version',
    buildTime: ar ? 'وقت البناء (ISO)' : 'Build time (ISO)',
    commit: ar ? 'الالتزام البرمجي' : 'Commit',
    branch: ar ? 'الفرع' : 'Branch',
    environment: ar ? 'البيئة' : 'Environment',
  }
})

const backendKeys = ['version', 'commit', 'branch', 'build_time', 'environment'] as const
const labelsByBackendKey = computed<Record<(typeof backendKeys)[number], string>>(() => ({
  version: labels.value.version,
  commit: labels.value.commit,
  branch: labels.value.branch,
  build_time: labels.value.buildTime,
  environment: labels.value.environment,
}))

function displayVal(v: unknown): string {
  if (v === null || v === undefined) return '—'
  return String(v)
}

const parityOk = computed(() => {
  const b = backend.value
  if (!b) return false
  const timeOk = !b.build_time || b.build_time === buildInfo.buildTime

  return (
    b.version === buildInfo.version &&
    b.commit === buildInfo.commit &&
    b.branch === buildInfo.branch &&
    timeOk &&
    b.environment === buildInfo.environment
  )
})

const parityHint = computed(() => {
  if (!backend.value) return ''
  if (parityOk.value) {
    return '✓ تطابق كامل بين الواجهة والخادم — يُعتمد هذا كنشر متسق.'
  }
  return '⚠ اختلاف: اضبط APP_RELEASE_* في الخادم على نفس قيم بناء الواجهة (CI)، أو أعد بناء الواجهة والخادم معًا.'
})

async function loadBackend() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await apiClient.get('/system/version')
    backend.value = data.data ?? null
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? 'تعذّر جلب بيانات النسخة من الخادم'
    backend.value = null
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadBackend()
})
</script>
