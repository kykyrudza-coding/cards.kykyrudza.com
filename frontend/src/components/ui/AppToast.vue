<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useUiStore } from '../../stores/ui'
import AppIcon from './AppIcon.vue'
const ui = useUiStore()
const { t } = useI18n()
</script>
<template>
  <div class="toast-region" aria-live="polite" aria-atomic="false">
    <TransitionGroup name="toast"
      ><div v-for="toast in ui.toasts" :key="toast.id" class="toast" :class="`toast-${toast.tone}`">
        <AppIcon :name="toast.tone === 'success' ? 'check' : 'volume'" /><span>{{
          toast.message
        }}</span
        ><button
          class="icon-button"
          type="button"
          :aria-label="t('ui.dismissNotification')"
          @click="ui.dismiss(toast.id)"
        >
          <AppIcon name="close" :size="16" />
        </button></div
    ></TransitionGroup>
  </div>
</template>
