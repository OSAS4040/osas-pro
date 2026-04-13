<template>
  <div class="flex flex-wrap gap-2">
    <RouterLink to="/admin/registration-profiles" class="inline-flex items-center gap-1 rounded-xl border border-violet-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-violet-900 shadow-sm hover:bg-violet-50 dark:border-violet-800 dark:bg-slate-800 dark:text-violet-100">Registrations</RouterLink>
    <RouterLink to="/admin/qa" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">QA</RouterLink>
    <RouterLink to="/reports" class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-800 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">Reports</RouterLink>
    <button type="button" class="inline-flex items-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-900 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100" @click="$emit('refresh')">Refresh</button>
  </div>
</template>

<script setup lang="ts">
defineEmits<{ (e: 'refresh'): void }>()
</script>
