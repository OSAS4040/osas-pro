<template>
  <div class="app-shell-page" dir="rtl">
    <div class="max-w-4xl space-y-6">
      <div class="page-head">
        <div class="page-title-wrap">
          <h2 class="page-title-xl">الأجهزة والجلسات</h2>
          <p class="page-subtitle">
            عرض الجلسات النشطة على حسابك، وإنهاء الجلسات الأخرى بأمان. لا يُعرض عنوان IP كاملاً لأسباب خصوصية.
          </p>
        </div>
      </div>

      <div v-if="loadError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-100">
        {{ loadError }}
      </div>

      <div v-else-if="loading" class="rounded-xl border border-gray-100 bg-white p-8 text-center text-sm text-gray-500 dark:border-slate-800 dark:bg-slate-900">
        جاري التحميل…
      </div>

      <template v-else>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="btn btn-secondary text-sm"
            :disabled="actionBusy || sessions.length < 2"
            @click="onRevokeOthers"
          >
            تسجيل الخروج من الأجهزة الأخرى
          </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-right text-xs font-semibold uppercase text-gray-500 dark:bg-slate-800 dark:text-slate-400">
              <tr>
                <th class="px-4 py-3">الجهاز</th>
                <th class="px-4 py-3">القناة</th>
                <th class="px-4 py-3">المتصفح / الوكيل</th>
                <th class="px-4 py-3">ملخص IP</th>
                <th class="px-4 py-3">آخر نشاط</th>
                <th class="px-4 py-3">أُنشئت</th>
                <th class="px-4 py-3">الحالة</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
              <tr v-for="s in sessions" :key="s.id" class="text-gray-800 dark:text-slate-100">
                <td class="px-4 py-3 font-medium">{{ s.device_name }}</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ s.auth_channel || '—' }}</td>
                <td class="px-4 py-3 text-xs">{{ s.user_agent_summary || '—' }}</td>
                <td class="px-4 py-3 text-xs font-mono">{{ s.ip_summary || '—' }}</td>
                <td class="px-4 py-3 text-xs whitespace-nowrap">{{ formatTs(s.last_used_at) }}</td>
                <td class="px-4 py-3 text-xs whitespace-nowrap">{{ formatTs(s.created_at) }}</td>
                <td class="px-4 py-3">
                  <span
                    v-if="s.is_current"
                    class="inline-flex rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-semibold text-primary-800 dark:bg-primary-900/40 dark:text-primary-200"
                  >الجلسة الحالية</span>
                  <span v-else class="text-xs text-gray-400">أخرى</span>
                </td>
                <td class="px-4 py-3 text-left">
                  <button
                    v-if="!s.is_current"
                    type="button"
                    class="text-xs font-semibold text-red-600 hover:underline disabled:opacity-40"
                    :disabled="actionBusy"
                    @click="onRevokeOne(s.id)"
                  >
                    إنهاء
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useApi } from '@/composables/useApi'

type SessionRow = {
  id: number
  device_name: string
  auth_channel: string | null
  ip_summary: string | null
  user_agent_summary: string | null
  last_used_at: string | null
  created_at: string | null
  is_current: boolean
}

const api = useApi()
const sessions = ref<SessionRow[]>([])
const loading = ref(true)
const loadError = ref('')
const actionBusy = ref(false)

function formatTs(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Date(iso).toLocaleString('ar-SA', { hour12: true })
  } catch {
    return iso
  }
}

async function loadSessions(): Promise<void> {
  loading.value = true
  loadError.value = ''
  try {
    const res = await api.get('/api/v1/auth/sessions')
    sessions.value = Array.isArray(res.data) ? res.data : []
  } catch (e: any) {
    loadError.value = e?.response?.data?.message || 'تعذّر تحميل الجلسات.'
  } finally {
    loading.value = false
  }
}

async function onRevokeOne(id: number): Promise<void> {
  if (!confirm('إنهاء هذه الجلسة؟ سيتم تسجيل الخروج من هذا الجهاز فقط.')) return
  actionBusy.value = true
  try {
    await api.del(`/api/v1/auth/sessions/${id}`)
    await loadSessions()
  } catch (e: any) {
    alert(e?.response?.data?.message || 'تعذّر إنهاء الجلسة.')
  } finally {
    actionBusy.value = false
  }
}

async function onRevokeOthers(): Promise<void> {
  if (!confirm('تسجيل الخروج من جميع الأجهزة الأخرى؟ ستبقى هذه الجلسة فقط نشطة.')) return
  actionBusy.value = true
  try {
    await api.post('/api/v1/auth/sessions/revoke-others', {})
    await loadSessions()
  } catch (e: any) {
    alert(e?.response?.data?.message || 'تعذّر تنفيذ العملية.')
  } finally {
    actionBusy.value = false
  }
}

onMounted(() => {
  void loadSessions()
})
</script>
