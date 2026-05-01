<template>
  <aside class="hidden w-72 shrink-0 border-l border-slate-800 bg-slate-950 text-slate-200 xl:block">
    <div class="border-b border-slate-800 px-4 py-4">
      <p class="text-[10px] uppercase tracking-wide text-violet-300">OSAS PRO</p>
      <p class="mt-1 text-sm font-black">مركز قيادة المنصة</p>
    </div>
    <nav class="space-y-1 p-3">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        class="w-full rounded-xl px-3 py-2 text-right text-sm transition"
        :class="active === item.id ? 'bg-violet-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
        @click="$emit('jump', item.id)"
      >
        {{ item.label }}
      </button>
    </nav>
    <div class="border-t border-slate-800 p-3">
      <div class="rounded-xl border border-violet-800/60 bg-violet-950/30 p-3">
        <p class="text-xs font-bold text-violet-200">دعم سريع</p>
        <p class="mt-1 text-[11px] text-slate-300">احتجت تدخل مباشر؟ افتح تذكرة أو مركز المساعدة.</p>
        <div class="mt-2 space-y-1">
          <button type="button" class="w-full rounded-lg bg-violet-600 px-2 py-1.5 text-xs font-bold text-white" @click="$emit('open-ticket')">فتح تذكرة دعم</button>
          <button type="button" class="w-full rounded-lg border border-slate-700 px-2 py-1.5 text-xs" @click="$emit('open-help')">مركز المساعدة</button>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
defineProps<{ active: string; items: Array<{ id: string; label: string }> }>()
defineEmits<{ jump: [id: string]; 'open-ticket': []; 'open-help': [] }>()
</script>
