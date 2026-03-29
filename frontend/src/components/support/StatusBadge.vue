<template>
  <span :class="item.cls" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
    {{ item.label }}
  </span>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ status: string }>()
const map: Record<string, { cls: string; label: string }> = {
  open:             { cls: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',       label: 'مفتوحة' },
  in_progress:      { cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300', label: 'قيد المعالجة' },
  pending_customer: { cls: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300', label: 'انتظار العميل' },
  resolved:         { cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',   label: 'محلولة' },
  closed:           { cls: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',          label: 'مغلقة' },
  escalated:        { cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',           label: '⚠️ مصعّدة' },
}
const item = computed(() => map[props.status] ?? { cls: 'bg-gray-100 text-gray-600', label: props.status })
</script>
