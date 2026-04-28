<template>
  <details class="mt-6 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
    <summary class="cursor-pointer select-none text-sm font-semibold text-slate-800 dark:text-slate-100">
      حمولة API كاملة (قراءة فقط — للتحقق من عدم فقد الحقول)
    </summary>
    <pre
      class="mt-3 max-h-[min(70vh,560px)] overflow-auto rounded-lg bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-900 dark:bg-slate-950 dark:text-slate-100"
      dir="ltr"
    >{{ text }}</pre>
  </details>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ payload: unknown }>()
const text = computed(() => JSON.stringify(props.payload ?? {}, null, 2))
</script>
