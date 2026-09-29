<script setup lang="ts">
defineProps<{ label: string; hint?: string; disabled?: boolean }>()
const model = defineModel<boolean>({ required: true })
</script>
<template>
  <label class="toggle-row"
    ><span
      >{{ label }}<small v-if="hint">{{ hint }}</small></span
    ><input v-model="model" type="checkbox" role="switch" :disabled="disabled" /><span
      class="switch-track"
      aria-hidden="true"
  /></label>
</template>
