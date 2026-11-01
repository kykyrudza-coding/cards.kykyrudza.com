<script setup lang="ts">
import { computed } from 'vue'
import { resolveApiUrl } from '../../services/api'
const props = defineProps<{ name: string; src?: string | null }>()
const url = computed(() => resolveApiUrl(props.src))
</script>
<template>
  <span class="avatar" :aria-label="name"
    ><img v-if="url" :src="url" alt="" loading="lazy" decoding="async" /><span v-else>{{
      name.slice(0, 2).toUpperCase()
    }}</span></span
  >
</template>
