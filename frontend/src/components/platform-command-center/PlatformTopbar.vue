<template>
  <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 px-5 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-lg font-black text-slate-900 dark:text-slate-100">مركز التحكم</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">إدارة شاملة لمنصة OSAS</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          @click="$emit('go-back')"
        >
          رجوع
        </button>
        <button
          type="button"
          class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          @click="$emit('go-home')"
        >
          العودة للتطبيق
        </button>
        <div class="relative min-w-[260px] max-w-[420px] flex-1">
          <input
            :value="search"
            type="search"
            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 dark:border-slate-700 dark:bg-slate-900"
            placeholder="بحث شامل: شركات، مستخدمين، طلبات، فواتير، دعم"
            @input="$emit('update:search', ($event.target as HTMLInputElement).value)"
          />
        </div>
        <button
          type="button"
          class="rounded-xl bg-violet-600 px-3 py-2 text-xs font-bold text-white hover:bg-violet-500"
          @click="$emit('refresh')"
        >
          تحديث البيانات
        </button>
        <button
          type="button"
          class="rounded-xl border border-slate-300 p-2 text-slate-600 dark:border-slate-700 dark:text-slate-300"
          @click="$emit('open-notifications')"
        >
          🔔
        </button>
        <button
          type="button"
          class="rounded-xl border border-slate-300 p-2 text-slate-600 dark:border-slate-700 dark:text-slate-300"
          @click="$emit('open-help')"
        >
          ؟
        </button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
defineProps<{ search: string }>()
defineEmits<{ 'update:search': [value: string]; refresh: []; 'go-back': []; 'go-home': []; 'open-notifications': []; 'open-help': [] }>()
</script>
