<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
    loading?: boolean
    disabled?: boolean
    type?: 'button' | 'submit' | 'reset'
  }>(),
  { variant: 'secondary', type: 'button' },
)
</script>
<template>
  <button
    :type="type"
    class="btn"
    :class="`btn-${variant}`"
    :disabled="disabled || loading"
    :aria-busy="loading"
  >
    <span v-if="loading" class="loader" aria-hidden="true" /><slot />
  </button>
</template>
