<script setup lang="ts">
import { useId } from 'vue'
defineProps<{ text: string }>()
const id = useId()
</script>
<template>
  <span class="tooltip" tabindex="0" :aria-describedby="id"
    ><slot /><span :id="id" role="tooltip" class="tooltip-content">{{ text }}</span></span
  >
</template>
