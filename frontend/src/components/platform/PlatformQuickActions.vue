<template>
  <div class="flex flex-wrap gap-2">
    <RouterLink
      to="/platform/companies"
      class="inline-flex shrink-0 items-center gap-1 self-center rounded-xl border border-primary-200 bg-primary-50 px-2.5 py-1.5 text-[11px] font-bold text-primary-900 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/40 dark:text-primary-100"
    >
      عرض الشركات
    </RouterLink>
    <RouterLink
      to="/platform/finance"
      class="inline-flex shrink-0 items-center gap-1 self-center rounded-xl border border-primary-200 bg-primary-50 px-2.5 py-1.5 text-[11px] font-bold text-primary-900 hover:bg-primary-100 dark:border-primary-800 dark:bg-primary-900/40 dark:text-primary-100"
    >
      عرض المالية
    </RouterLink>
    <PlatformOperationsExitLink
      to="/admin/registration-profiles"
      ariaName="طلبات التسجيل"
      variant="toolbar"
    >
      طلبات التسجيل
    </PlatformOperationsExitLink>
    <PlatformOperationsExitLink to="/admin/qa" ariaName="اختبار النظام" variant="toolbar">
      اختبار النظام
    </PlatformOperationsExitLink>
    <PlatformOperationsExitLink to="/reports" ariaName="التقارير في فريق العمل" variant="toolbar">
      التقارير (فريق العمل)
    </PlatformOperationsExitLink>
    <button
      type="button"
      title="إعادة جلب بيانات الملخص من الخادم"
      class="inline-flex shrink-0 items-center gap-1 self-center rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-900 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100"
      @click="$emit('refresh')"
    >
      تحديث البيانات
    </button>
  </div>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import PlatformOperationsExitLink from '@/components/platform-admin/PlatformOperationsExitLink.vue'

defineEmits<{ (e: 'refresh'): void }>()
</script>
