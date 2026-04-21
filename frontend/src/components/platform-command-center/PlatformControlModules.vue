<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">وحدات إدارة المنصة</h3>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">تحكم سريع ومباشر بكل مسارات الإدارة الحيوية.</p>
    <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="m in modules"
        :key="m.key"
        class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 dark:border-slate-700 dark:bg-slate-800/50"
      >
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ m.title }}</p>
        <p class="mt-1 text-xl font-black text-violet-700 dark:text-violet-300">{{ m.value }}</p>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ m.desc }}</p>
        <div class="mt-2 flex gap-2">
          <button
            type="button"
            class="rounded-lg bg-violet-600 px-2.5 py-1.5 text-xs font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="Boolean(m.disabled)"
            :title="m.disabledReason || ''"
            @click="$emit('open', m.key, m.path)"
          >
            فتح
          </button>
          <button
            v-if="m.secondaryPath"
            type="button"
            class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700"
            :disabled="Boolean(m.disabled)"
            :title="m.disabledReason || ''"
            @click="$emit('open', m.key, m.secondaryPath)"
          >
            إدارة
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
defineProps<{
  modules: Array<{
    key: string
    title: string
    value: string
    desc: string
    path: string
    secondaryPath?: string
    disabled?: boolean
    disabledReason?: string
  }>
}>()
defineEmits<{ open: [key: string, path: string] }>()
</script>
