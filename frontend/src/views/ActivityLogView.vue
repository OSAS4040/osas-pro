<template>
  <div class="space-y-6 max-w-4xl mx-auto" dir="rtl">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-gray-900 dark:text-slate-100">سجل العمليات</h1>
        <p class="text-sm text-gray-500 dark:text-slate-400 mt-1">
          يُسجّل تلقائياً الانتقال بين الصفحات في النظام (محلياً على هذا الجهاز).
        </p>
      </div>
      <button
        type="button"
        class="px-4 py-2 text-sm rounded-xl border border-red-200 text-red-700 dark:border-red-900 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30"
        @click="clearAll"
      >
        مسح السجل
      </button>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 overflow-hidden shadow-sm">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-slate-700 text-sm font-semibold text-gray-800 dark:text-slate-100">
        آخر {{ entries.length }} حدثاً
      </div>
      <ul v-if="entries.length" class="divide-y divide-gray-100 dark:divide-slate-700 max-h-[min(70vh,560px)] overflow-y-auto">
        <li v-for="(e, i) in entries" :key="i" class="px-4 py-3 flex flex-wrap gap-2 items-start text-sm">
          <span class="text-xs text-gray-400 tabular-nums whitespace-nowrap">{{ formatAt(e.at) }}</span>
          <span class="font-medium text-primary-700 dark:text-primary-400">{{ e.action }}</span>
          <span class="text-gray-500 dark:text-slate-400 font-mono text-xs dir-ltr">{{ e.path }}</span>
          <span v-if="e.detail" class="text-gray-600 dark:text-slate-300 text-xs w-full">{{ e.detail }}</span>
        </li>
      </ul>
      <p v-else class="px-4 py-16 text-center text-gray-400 text-sm">لا توجد أحداث بعد — تصفّح النظام ليُبنى السجل.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getActivityLog, clearActivityLog, type ActivityEntry } from '@/composables/useActivityLog'
import { useToast } from '@/composables/useToast'

const toast = useToast()
const entries = ref<ActivityEntry[]>([])

function formatAt(iso: string) {
  try {
    return new Date(iso).toLocaleString('ar-SA', {
      dateStyle: 'short',
      timeStyle: 'short',
    })
  } catch {
    return iso
  }
}

function refresh() {
  entries.value = getActivityLog()
}

function clearAll() {
  clearActivityLog()
  entries.value = []
  toast.success('تم مسح سجل العمليات')
}

onMounted(refresh)
</script>
