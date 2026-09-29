<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'success' | 'danger' | 'warning' | 'neutral' }>(), {
  tone: 'neutral',
})
</script>
<template>
  <span class="badge" :class="`badge-${tone}`"><slot /></span>
</template>
