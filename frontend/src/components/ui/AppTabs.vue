<script setup lang="ts">
defineProps<{ tabs: { id: string; label: string; disabled?: boolean }[]; label?: string }>()
const model = defineModel<string>({ required: true })
</script>
<template>
  <div class="tabs" role="group" :aria-label="label ?? 'Filter'">
    <button
      v-for="tab in tabs"
      :key="tab.id"
      type="button"
      :disabled="tab.disabled"
      :aria-pressed="model === tab.id"
      :class="{ selected: model === tab.id }"
      @click="model = tab.id"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
