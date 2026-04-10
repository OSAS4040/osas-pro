<template>
  <div class="max-w-3xl mx-auto space-y-5 py-2" dir="rtl">
    <header class="rounded-2xl border border-primary-200/60 dark:border-primary-900/40 bg-gradient-to-br from-primary-50/80 to-white dark:from-slate-900 dark:to-slate-800 p-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ title }}</h1>
      <p class="text-sm text-gray-600 dark:text-slate-400 mt-2 leading-relaxed">{{ subtitle }}</p>
    </header>
    <div class="rounded-xl border border-amber-200/80 bg-amber-50/90 dark:bg-amber-950/30 dark:border-amber-900/50 p-4 text-sm text-amber-900 dark:text-amber-200">
      هذه الوحدة <strong>مفعّلة في التنقل</strong> وجاهزة للربط التدريجي بالواجهات الخلفية والتكاملات (إشعارات، أرشفة، توقيع، إلخ).
    </div>
    <ul class="text-sm text-gray-600 dark:text-slate-400 space-y-2 list-disc list-inside">
      <li v-for="(b, i) in bullets" :key="i">{{ b }}</li>
    </ul>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => String(route.meta.titleAr ?? 'وحدة'))
const subtitle = computed(() => String(route.meta.subtitleAr ?? ''))
const bullets = computed(() => (Array.isArray(route.meta.bullets) ? route.meta.bullets as string[] : []))
</script>
