<script setup lang="ts">
import { useId } from 'vue'
defineProps<{ label: string; disabled?: boolean }>()
const model = defineModel<number>({ required: true })
const id = useId()
</script>
<template>
  <div class="field">
    <label :for="id" class="row-between"
      >{{ label }} <span class="muted">{{ model }}%</span></label
    ><input :id="id" v-model.number="model" type="range" min="0" max="100" :disabled="disabled" />
  </div>
</template>
