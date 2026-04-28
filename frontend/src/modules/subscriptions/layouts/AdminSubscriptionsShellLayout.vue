<template>
  <div class="mx-auto flex max-w-[1600px] flex-col gap-4" dir="rtl">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <RouterLink
        :to="{ name: 'platform-overview' }"
        class="text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300"
      >
        ← العودة لملخص المنصة
      </RouterLink>
      <span class="text-[11px] text-slate-500 dark:text-slate-400">مسار تشغيل الاشتراكات والدفع</span>
    </div>
    <AdminSubscriptionsNav :attention-total="injected?.badgeCount ?? 0" />
    <router-view />
  </div>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import AdminSubscriptionsNav from '../components/AdminSubscriptionsNav.vue'
import { platformSubscriptionAttentionKey } from '@/components/platform-admin/PlatformSubscriptionAttentionInjectKey'

const injected = inject(platformSubscriptionAttentionKey, null)
</script>
