<template>
  <div class="rounded-2xl border border-rose-200 dark:border-rose-900/50 bg-rose-50/80 dark:bg-rose-950/25 px-5 py-4 text-rose-900 dark:text-rose-100 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <p>{{ message }}</p>
    <button type="button" class="btn btn-primary text-sm self-start sm:self-auto" @click="$emit('retry')">{{ retry }}</button>
  </div>
</template>

<script setup lang="ts">
defineProps<{ message: string; retry: string }>()
defineEmits<{ retry: [] }>()
</script>
