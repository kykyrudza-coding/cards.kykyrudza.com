<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { assets } from '../../config/assets'
import AnimatedChips from './AnimatedChips.vue'
const props = withDefaults(defineProps<{ amount: number; chipSkin?: string; label?: string }>(), {
  chipSkin: 'classic',
})
const { t } = useI18n()
</script>
<template>
  <span class="chip-stack"
    ><span class="chip-stack-art" aria-hidden="true"
      ><img :src="assets.chip(25, chipSkin)" alt="" /><img
        :src="assets.chip(25, chipSkin)"
        alt="" /><img :src="assets.chip(25, chipSkin)" alt="" /></span
    ><span
      ><small>{{ props.label ?? t('match.hud.bet') }}</small
      ><strong><AnimatedChips :value="amount" /></strong></span
  ></span>
</template>
