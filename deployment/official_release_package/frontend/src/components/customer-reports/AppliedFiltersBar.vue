<template>
  <div
    v-if="chips.length"
    class="flex flex-wrap gap-2 text-xs text-slate-600 dark:text-slate-300"
  >
    <span class="font-medium text-slate-500 dark:text-slate-400">{{ title }}:</span>
    <span
      v-for="c in chips"
      :key="c.key"
      class="inline-flex items-center rounded-lg bg-slate-100/90 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-600 px-2 py-0.5"
    >{{ c.label }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  filters: Record<string, unknown>
  ar: boolean
}>()

const title = computed(() => (props.ar ? 'الفلاتر المطبّقة' : 'Applied filters'))

const chips = computed(() => {
  const f = props.filters ?? {}
  const out: { key: string; label: string }[] = []
  if (f.branch_ids && Array.isArray(f.branch_ids)) {
    out.push({ key: 'br', label: props.ar ? `فروع: ${(f.branch_ids as number[]).join(', ')}` : `Branches: ${(f.branch_ids as number[]).join(', ')}` })
  } else if (!f.branch_ids) {
    out.push({ key: 'all', label: props.ar ? 'كل الفروع المسموحة' : 'All allowed branches' })
  }
  if (f.customer_id) {
    out.push({ key: 'cu', label: props.ar ? `عميل #${f.customer_id}` : `Customer #${f.customer_id}` })
  }
  return out
})
</script>
