<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import ChipPile from './ChipPile.vue'
import AnimatedChips from './AnimatedChips.vue'
const props = withDefaults(defineProps<{ amount: number; chipSkin?: string; label?: string }>(), {
  chipSkin: 'classic',
})
const { t } = useI18n()
</script>
<template>
  <span class="chip-stack">
    <ChipPile :amount="amount" :chip-skin="chipSkin" :max-height="6" class="chip-stack-art" />
    <span>
      <small>{{ props.label ?? t('match.hud.bet') }}</small>
      <strong><AnimatedChips :value="amount" /></strong>
    </span>
  </span>
</template>
