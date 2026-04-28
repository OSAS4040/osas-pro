<template>
  <div class="mx-auto max-w-3xl space-y-4" dir="rtl">
    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ title }}</h1>
    <p v-if="hint" class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ hint }}</p>
    <div class="rounded-xl border border-amber-200 bg-amber-50/90 p-4 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
      هذه الشاشة واجهة أولية؛ مسارات الـ API تحت
      <code class="rounded bg-white/80 px-1 font-mono text-xs dark:bg-slate-900/80" dir="ltr">/api/v1/platform/pricing/*</code>
      و
      <code class="rounded bg-white/80 px-1 font-mono text-xs dark:bg-slate-900/80" dir="ltr">/api/v1/platform/providers/*</code>
      جاهزة مع الحوكمة (إنشاء → مراجعة → اعتماد → تفعيل نسخة سعر).
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => String(route.meta.platformPricingTitle ?? 'إدارة المنصة'))
const hint = computed(() => (route.meta.platformPricingHint != null ? String(route.meta.platformPricingHint) : ''))
</script>
