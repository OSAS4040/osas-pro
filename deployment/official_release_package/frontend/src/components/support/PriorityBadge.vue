<template>
  <span :class="cls" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold">
    <span>{{ dot }}</span>{{ label }}
  </span>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ priority: string }>()
const map: Record<string, { cls: string; dot: string; label: string }> = {
  critical: { cls: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',    dot: '🔴', label: 'حرجة' },
  high:     { cls: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400', dot: '🟠', label: 'عالية' },
  medium:   { cls: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400', dot: '🟡', label: 'متوسطة' },
  low:      { cls: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',     dot: '🟢', label: 'منخفضة' },
}
const item = computed(() => map[props.priority] ?? { cls: 'bg-gray-100 text-gray-600', dot: '⚪', label: props.priority })
const cls   = computed(() => item.value.cls)
const dot   = computed(() => item.value.dot)
const label = computed(() => item.value.label)
</script>
