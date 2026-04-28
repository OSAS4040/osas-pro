<template>
  <PlatformSection :title="title" :subtitle="subtitle" :section-id="sectionId">
    <slot />
  </PlatformSection>
</template>

<script setup lang="ts">
import PlatformSection from '@/components/platform-admin/ui/PlatformSection.vue'

defineProps<{
  title: string
  subtitle?: string
  sectionId?: string
}>()
</script>
