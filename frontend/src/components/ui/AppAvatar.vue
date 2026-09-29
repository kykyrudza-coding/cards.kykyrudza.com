<script setup lang="ts">
defineProps<{ name: string; src?: string | null }>()
</script>
<template>
  <span class="avatar" :aria-label="name"
    ><img v-if="src" :src="src" alt="" /><span v-else>{{
      name.slice(0, 2).toUpperCase()
    }}</span></span
  >
</template>
