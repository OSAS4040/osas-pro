<template>
  <div class="min-h-screen bg-slate-50 p-4 dark:bg-slate-950" dir="rtl">
    <div class="mx-auto max-w-5xl">
      <div class="mb-4 flex items-center justify-between gap-2">
        <RouterLink
          to="/platform/overview"
          class="text-sm font-semibold text-primary-700 underline decoration-primary-300 underline-offset-2 transition-colors hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
        >
          ← لوحة المنصة
        </RouterLink>
        <button type="button" class="text-xs text-slate-500 underline" @click="load">تحديث</button>
      </div>
      <h1 class="text-xl font-bold text-slate-900 dark:text-white">طلبات التسجيل بالجوال</h1>
      <p v-if="err" class="mt-2 text-sm text-red-600">{{ err }}</p>
      <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <table class="min-w-full text-right text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
            <tr>
              <th class="p-2">#</th>
              <th class="p-2">جوال</th>
              <th class="p-2">منشأة</th>
              <th class="p-2">مسؤول</th>
              <th class="p-2">حالة</th>
              <th class="p-2">تفعيل</th>
              <th class="p-2">%</th>
              <th class="p-2">إجراءات</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id" class="border-b border-slate-100 dark:border-slate-800">
              <td class="p-2">{{ row.id }}</td>
              <td class="p-2 font-mono" dir="ltr">{{ row.user?.phone }}</td>
              <td class="p-2">{{ row.company_name || '—' }}</td>
              <td class="p-2">{{ row.contact_name || '—' }}</td>
              <td class="p-2">{{ row.status }}</td>
              <td class="p-2">{{ row.company_activation_status }}</td>
              <td class="p-2">{{ row.profile_completion_percent }}</td>
              <td class="p-2 space-x-1 space-x-reverse">
                <button type="button" class="text-emerald-700 underline" @click="approve(row.id)">اعتماد</button>
                <button type="button" class="text-red-700 underline" @click="reject(row.id)">رفض</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import apiClient from '@/lib/apiClient'

const rows = ref<any[]>([])
const err = ref('')

async function load(): Promise<void> {
  err.value = ''
  try {
    const { data } = await apiClient.get('/platform/registration-profiles')
    const body = data.data
    rows.value = Array.isArray(body?.data) ? body.data : Array.isArray(body) ? body : []
  } catch {
    err.value = 'تعذّر التحميل — تحقق من صلاحيات مشغّل المنصة.'
  }
}

async function approve(id: number): Promise<void> {
  await apiClient.post(`/platform/registration-profiles/${id}/approve`, {})
  await load()
}

async function reject(id: number): Promise<void> {
  await apiClient.post(`/platform/registration-profiles/${id}/reject`, { notes: 'مرفوض من الواجهة' })
  await load()
}

onMounted(() => {
  void load()
})
</script>
