<template>
  <section>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="card in cards"
        :key="card.key"
        class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-slate-900"
        :class="card.className"
      >
        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ card.label }}</p>
        <p class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">{{ card.value }}</p>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ card.delta }}</p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{
  cards: Array<{ key: string; label: string; value: string; delta: string; className: string }>
}>()
</script>
