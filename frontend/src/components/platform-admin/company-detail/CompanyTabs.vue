<template>
  <div class="rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-900/70">
    <div class="flex flex-wrap gap-1">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        class="rounded-lg px-3 py-2 text-xs font-bold transition"
        :class="activeTab === tab.id ? 'bg-primary-600 text-white' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'"
        @click="$emit('update:activeTab', tab.id)"
      >
        {{ tab.label }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
interface TabItem {
  id: string
  label: string
}

defineProps<{
  tabs: TabItem[]
  activeTab: string
}>()

defineEmits<{
  'update:activeTab': [value: string]
}>()
</script>
