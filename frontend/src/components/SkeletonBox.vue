<template>
  <div :class="[
    'animate-pulse bg-gradient-to-r from-gray-200 via-gray-100 to-gray-200 bg-[length:400%_100%] rounded-lg',
    props.class
  ]" :style="{ width: props.width, height: props.height }"
  />
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
  width?: string
  height?: string
  class?: string
}>(), {
  width: '100%',
  height: '1rem',
  class: '',
})
</script>

<style scoped>
@keyframes shimmer {
  0%   { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}
.animate-pulse {
  animation: shimmer 1.4s ease-in-out infinite;
}
</style>
