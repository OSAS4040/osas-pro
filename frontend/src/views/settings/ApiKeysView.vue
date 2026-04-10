<template>
  <div class="p-6 space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800 dark:text-slate-100">API Keys</h1>
      <button class="btn btn-primary text-xs" @click="createKey">إضافة API Key</button>
    </div>

    <div v-if="newToken" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
      المفتاح الجديد (يظهر مرة واحدة): <span class="font-mono">{{ newToken }}</span>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-slate-100">تنبيهات أمن API</h2>
        <span class="text-[11px] text-gray-400">آخر 50 طلب</span>
      </div>
      <div v-if="alerts.length" class="space-y-2">
        <div
          v-for="(a, idx) in alerts"
          :key="idx"
          class="rounded-lg border px-3 py-2 text-xs"
          :class="a.level === 'high'
            ? 'border-red-200 bg-red-50 text-red-800'
            : a.level === 'medium'
              ? 'border-amber-200 bg-amber-50 text-amber-800'
              : 'border-sky-200 bg-sky-50 text-sky-800'"
        >
          <span class="font-semibold">{{ a.title }}:</span>
          {{ a.message }}
        </div>
      </div>
      <p v-else class="text-xs text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">
        لا توجد مؤشرات خطورة حالياً.
      </p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full text-sm bg-white dark:bg-slate-800">
        <thead class="bg-gray-50 dark:bg-slate-900/60 text-gray-500 dark:text-slate-400 text-xs">
          <tr>
            <th class="px-3 py-2 text-right">الاسم</th>
            <th class="px-3 py-2 text-right">الحد/دقيقة</th>
            <th class="px-3 py-2 text-right">تاريخ الانتهاء</th>
            <th class="px-3 py-2 text-right">آخر استخدام</th>
            <th class="px-3 py-2 text-right">الحالة</th>
            <th class="px-3 py-2 text-right">إجراء</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="k in keys" :key="k.key_id" class="border-t border-gray-100 dark:border-slate-700">
            <td class="px-3 py-2">
              <input v-model="k.name" class="w-full border rounded px-2 py-1 text-xs" />
            </td>
            <td class="px-3 py-2">
              <input v-model.number="k.rate_limit" type="number" min="1" max="100000" class="w-24 border rounded px-2 py-1 text-xs" />
            </td>
            <td class="px-3 py-2">
              <SmartDatePicker :model-value="k.expires_at_local" mode="single" @change="(val) => onKeyExpiryChange(k, val)" />
            </td>
            <td class="px-3 py-2">{{ k.created_at || '—' }}</td>
            <td class="px-3 py-2">
              <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">
                نشط
              </span>
            </td>
            <td class="px-3 py-2">
              <div class="flex items-center gap-2">
                <button class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700" @click="saveKey(k)">
                  حفظ
                </button>
                <button class="text-xs px-2 py-1 rounded bg-red-100 text-red-700" @click="revoke(k.key_id)">
                  إلغاء
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
      <div class="px-3 py-2 text-sm font-semibold bg-gray-50 dark:bg-slate-900/60">إحصاءات الاستخدام</div>
      <table class="w-full text-xs bg-white dark:bg-slate-800">
        <thead class="text-gray-500 dark:text-slate-400">
          <tr>
            <th class="px-3 py-2 text-right">التاريخ</th>
            <th class="px-3 py-2 text-right">المسار</th>
            <th class="px-3 py-2 text-right">الحالة</th>
            <th class="px-3 py-2 text-right">المدة</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in logs" :key="l.id" class="border-t border-gray-100 dark:border-slate-700">
            <td class="px-3 py-2">{{ l.created_at || '—' }}</td>
            <td class="px-3 py-2 font-mono">{{ l.path || '—' }}</td>
            <td class="px-3 py-2">{{ l.status_code ?? '—' }}</td>
            <td class="px-3 py-2">{{ l.duration_ms ?? '—' }} ms</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import apiClient from '@/lib/apiClient'
import SmartDatePicker from '@/components/ui/SmartDatePicker.vue'

const auth = useAuthStore()
const router = useRouter()

const keys = ref<any[]>([])
const logs = ref<any[]>([])
const newToken = ref('')
type AlertLevel = 'high' | 'medium' | 'info'
const alerts = computed<Array<{ title: string; message: string; level: AlertLevel }>>(() => {
  const out: Array<{ title: string; message: string; level: AlertLevel }> = []
  const now = new Date()

  const expSoon = keys.value.filter((k) => {
    if (!k.expires_at) return false
    const d = new Date(k.expires_at)
    const diffDays = Math.ceil((d.getTime() - now.getTime()) / 86400000)
    return diffDays >= 0 && diffDays <= 7
  })
  if (expSoon.length) {
    out.push({
      title: 'انتهاء قريب',
      message: `${expSoon.length} مفتاح/مفاتيح تنتهي خلال 7 أيام.`,
      level: 'medium',
    })
  }

  const lowRate = keys.value.filter((k) => Number(k.rate_limit || 0) < 30)
  if (lowRate.length) {
    out.push({
      title: 'حد طلبات منخفض',
      message: `${lowRate.length} مفتاح/مفاتيح بحد أقل من 30 طلب/دقيقة، قد يسبب انقطاع تكاملات.`,
      level: 'info',
    })
  }

  const errorLogs = logs.value.filter((l) => Number(l.status_code) >= 400)
  if (errorLogs.length >= 10) {
    out.push({
      title: 'ارتفاع أخطاء API',
      message: `${errorLogs.length} طلب فاشل ضمن آخر 50 سجل.`,
      level: 'high',
    })
  } else if (errorLogs.length >= 4) {
    out.push({
      title: 'أخطاء API ملحوظة',
      message: `${errorLogs.length} طلبات فاشلة ضمن آخر 50 سجل.`,
      level: 'medium',
    })
  }

  const slowLogs = logs.value.filter((l) => Number(l.duration_ms) >= 1500)
  if (slowLogs.length >= 8) {
    out.push({
      title: 'بطء مرتفع',
      message: `${slowLogs.length} طلبات تجاوزت 1500ms.`,
      level: 'medium',
    })
  }

  return out
})

const toastOff = { skipGlobalErrorToast: true as const }

async function load() {
  const keysRes = await apiClient.get('/api-keys', toastOff)
  const rows = keysRes.data?.data?.data ?? keysRes.data?.data ?? []
  keys.value = rows.map((k: any) => ({
    ...k,
    expires_at_local: k.expires_at ? String(k.expires_at).slice(0, 10) : '',
  }))
  const logsRes = await apiClient.get('/api-usage-logs', { ...toastOff, params: { per_page: 50 } })
  logs.value = logsRes.data?.data?.data ?? logsRes.data?.data ?? []
}

async function createKey() {
  const name = `key-${Date.now()}`
  const res = await apiClient.post('/api-keys', { name })
  newToken.value = res.data?.secret || ''
  await load()
}

async function revoke(id: string) {
  await apiClient.delete(`/api-keys/${id}`)
  await load()
}

async function saveKey(k: any) {
  await apiClient.patch(`/api-keys/${k.key_id}`, {
    name: k.name,
    rate_limit: Number(k.rate_limit),
    expires_at: k.expires_at_local || null,
  })
  await load()
}

function onKeyExpiryChange(k: any, val: { from: string; to: string }) {
  k.expires_at_local = val.from || val.to
}

onMounted(async () => {
  if (!auth.hasPermission('api_keys.manage')) {
    await router.replace({
      name: 'access-denied',
      query: { reason: 'permission', from: router.currentRoute.value.fullPath.slice(0, 512) },
    })
    return
  }
  try {
    await load()
  } catch {
    keys.value = []
    logs.value = []
  }
})
</script>
