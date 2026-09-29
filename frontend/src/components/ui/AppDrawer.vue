<script setup lang="ts">
import AppModal from './AppModal.vue'
defineProps<{ open: boolean; title: string }>()
defineEmits<{ close: [] }>()
</script>
<template>
  <AppModal :open="open" :title="title" drawer @close="$emit('close')"><slot /></AppModal>
</template>
