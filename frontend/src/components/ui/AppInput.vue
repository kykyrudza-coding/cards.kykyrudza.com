<script setup lang="ts">
import { useId } from 'vue'
defineOptions({ inheritAttrs: false })
defineProps<{ label: string; error?: string; hint?: string }>()
const model = defineModel<string | number>({ default: '' })
const id = useId()
</script>
<template>
  <div class="field">
    <label :for="id">{{ label }}</label
    ><input
      :id="id"
      v-model="model"
      v-bind="$attrs"
      :aria-invalid="!!error"
      :aria-describedby="error || hint ? `${id}-help` : undefined"
    /><small v-if="error || hint" :id="`${id}-help`" :class="{ error }">{{ error || hint }}</small>
  </div>
</template>
