<template>
  <div class="space-y-6 pb-10" dir="rtl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">الاجتماعات</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-0.5">
          مسارات داخلية للمنشأة — بدون تكامل فيديو أو تقويم خارجي في هذا الإصدار.
        </p>
      </div>
      <div class="flex gap-2">
        <button
          type="button"
          class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-slate-600"
          :disabled="loading"
          @click="loadMeetings"
        >
          تحديث
        </button>
        <button
          type="button"
          class="px-4 py-2 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-700"
          @click="openCreate = true"
        >
          + اجتماع جديد
        </button>
      </div>
    </div>

    <p v-if="listError" class="text-sm text-red-600 bg-red-50 dark:bg-red-950/30 rounded-lg px-3 py-2">{{ listError }}</p>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/40 overflow-hidden">
        <table class="w-full text-sm">
          <thead class="text-xs text-gray-500 bg-gray-50 dark:bg-slate-900/50">
            <tr>
              <th class="px-3 py-2 text-right">العنوان</th>
              <th class="px-3 py-2 text-right">الحالة</th>
              <th class="px-3 py-2 text-right">الموعد</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="m in meetingRows"
              :key="m.id"
              class="border-t border-gray-100 dark:border-slate-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800/80"
              :class="selected?.id === m.id ? 'bg-primary-50/50 dark:bg-primary-950/20' : ''"
              @click="selectMeeting(m)"
            >
              <td class="px-3 py-2 font-medium">{{ m.title }}</td>
              <td class="px-3 py-2"><span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-700">{{ m.status }}</span></td>
              <td class="px-3 py-2 text-xs font-mono">{{ m.scheduled_at ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!meetingRows.length && !loading" class="p-6 text-center text-gray-400 text-sm">لا توجد اجتماعات بعد.</p>
      </div>

      <div class="rounded-xl border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/40 p-4 space-y-3 min-h-[12rem]">
        <template v-if="selected">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">{{ selected.title }}</h3>
          <p class="text-xs text-gray-500">الحالة: {{ selected.status }}</p>
          <p v-if="selected.agenda" class="text-sm text-gray-700 dark:text-slate-300 whitespace-pre-wrap">{{ selected.agenda }}</p>
          <div class="flex flex-wrap gap-2 pt-2">
            <button
              v-for="a in availableActions"
              :key="a.to"
              type="button"
              class="px-3 py-1.5 text-xs rounded-lg border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40"
              :disabled="actionSaving"
              @click="transition(a.to)"
            >
              {{ a.label }}
            </button>
          </div>
          <p v-if="actionError" class="text-xs text-red-600">{{ actionError }}</p>
        </template>
        <p v-else class="text-sm text-gray-400">اختر اجتماعاً من القائمة.</p>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="openCreate" class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/50" @click.self="openCreate = false">
        <div class="bg-white dark:bg-slate-900 rounded-xl border max-w-md w-full p-5 space-y-3" @click.stop>
          <h3 class="font-bold">اجتماع جديد</h3>
          <div>
            <label class="text-xs text-gray-500">العنوان</label>
            <input v-model="createForm.title" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm dark:bg-slate-800 dark:border-slate-600" />
          </div>
          <div>
            <label class="text-xs text-gray-500">جدول الأعمال (اختياري)</label>
            <textarea v-model="createForm.agenda" rows="3" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm dark:bg-slate-800 dark:border-slate-600" />
          </div>
          <div>
            <label class="text-xs text-gray-500">موعد (اختياري)</label>
            <input v-model="createForm.scheduled_at" type="datetime-local" class="w-full mt-1 px-3 py-2 border rounded-lg text-sm dark:bg-slate-800 dark:border-slate-600" />
          </div>
          <p v-if="createError" class="text-xs text-red-600">{{ createError }}</p>
          <div class="flex justify-end gap-2">
            <button type="button" class="px-3 py-2 text-sm border rounded-lg" @click="openCreate = false">إلغاء</button>
            <button
              type="button"
              class="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg disabled:opacity-50"
              :disabled="createSaving || !createForm.title.trim()"
              @click="submitCreate"
            >
              {{ createSaving ? '...' : 'إنشاء' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import apiClient from '@/lib/apiClient'
import { localizeBackendMessage } from '@/utils/runtimeLocale'

type MeetingRow = {
  id: number
  title: string
  status: string
  agenda?: string | null
  scheduled_at?: string | null
}

const loading = ref(false)
const listError = ref('')
const listRaw = ref<unknown>(null)
const selected = ref<MeetingRow | null>(null)
const openCreate = ref(false)
const createForm = ref({ title: '', agenda: '', scheduled_at: '' })
const createSaving = ref(false)
const createError = ref('')
const actionSaving = ref(false)
const actionError = ref('')

const meetingRows = computed((): MeetingRow[] => {
  const raw = listRaw.value
  if (raw == null) return []
  if (Array.isArray(raw)) return raw as MeetingRow[]
  if (typeof raw === 'object' && raw !== null && Array.isArray((raw as { data?: unknown[] }).data)) {
    return (raw as { data: MeetingRow[] }).data
  }
  return []
})

const availableActions = computed(() => {
  const s = selected.value?.status
  if (!s) return [] as { label: string; to: string }[]
  const map: Record<string, { label: string; to: string }[]> = {
    draft: [
      { label: 'جدولة', to: 'scheduled' },
      { label: 'إلغاء', to: 'cancelled' },
    ],
    scheduled: [
      { label: 'بدء', to: 'in_progress' },
      { label: 'إغلاق', to: 'closed' },
      { label: 'إلغاء', to: 'cancelled' },
    ],
    in_progress: [
      { label: 'إغلاق', to: 'closed' },
      { label: 'إلغاء', to: 'cancelled' },
    ],
  }
  return map[s] ?? []
})

async function loadMeetings() {
  listError.value = ''
  loading.value = true
  try {
    const { data } = await apiClient.get('/meetings', { params: { per_page: 50 } })
    listRaw.value = data.data ?? null
    if (selected.value) {
      const u = meetingRows.value.find((m) => m.id === selected.value!.id)
      selected.value = u ?? null
    }
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    listError.value = localizeBackendMessage(msg) ?? 'تعذّر تحميل الاجتماعات.'
  } finally {
    loading.value = false
  }
}

function selectMeeting(m: MeetingRow) {
  selected.value = m
  actionError.value = ''
}

async function submitCreate() {
  createError.value = ''
  createSaving.value = true
  try {
    const body: Record<string, string> = { title: createForm.value.title.trim() }
    if (createForm.value.agenda.trim()) body.agenda = createForm.value.agenda.trim()
    if (createForm.value.scheduled_at) {
      body.scheduled_at = new Date(createForm.value.scheduled_at).toISOString()
    }
    const { data } = await apiClient.post('/meetings', body)
    const row = data.data as MeetingRow
    openCreate.value = false
    createForm.value = { title: '', agenda: '', scheduled_at: '' }
    await loadMeetings()
    selected.value = row
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    createError.value = localizeBackendMessage(msg) ?? 'تعذّر الإنشاء.'
  } finally {
    createSaving.value = false
  }
}

async function transition(to: string) {
  if (!selected.value) return
  actionError.value = ''
  actionSaving.value = true
  try {
    await apiClient.put(`/meetings/${selected.value.id}`, { status: to })
    await loadMeetings()
  } catch (e: unknown) {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
    actionError.value = localizeBackendMessage(msg) ?? 'انتقال الحالة غير مسموح أو فشل الطلب.'
  } finally {
    actionSaving.value = false
  }
}

onMounted(() => {
  void loadMeetings()
})
</script>
