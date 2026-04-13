<template>
  <div class="space-y-3">
    <div v-for="i in rows" :key="i" class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-0">
      <SkeletonBox height="2rem" width="2rem" class="rounded-full flex-shrink-0" />
      <div class="flex-1 space-y-2">
        <SkeletonBox height="0.875rem" :width="`${55 + (i * 13) % 35}%`" />
        <SkeletonBox height="0.75rem" :width="`${30 + (i * 17) % 25}%`" />
      </div>
      <SkeletonBox height="1.5rem" width="4rem" class="rounded-full" />
    </div>
  </div>
</template>

<script setup lang="ts">
import SkeletonBox from './SkeletonBox.vue'
withDefaults(defineProps<{ rows?: number }>(), { rows: 5 })
</script>
