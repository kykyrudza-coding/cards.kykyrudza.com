<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { gsap } from 'gsap'
import { useI18n } from 'vue-i18n'
import { formatChips } from '../../config/gameLabels'
import { usePreferencesStore } from '../../stores/preferences'
const props = defineProps<{ value: number }>()
const preferences = usePreferencesStore()
const { t } = useI18n()
const displayed = ref(props.value)
let tween: gsap.core.Tween | undefined
watch(
  () => props.value,
  (value) => {
    tween?.kill()
    if (
      preferences.reducedMotion ||
      !preferences.chipAnimations ||
      matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
      displayed.value = value
      return
    }
    const counter = { value: displayed.value }
    tween = gsap.to(counter, {
      value,
      duration: 0.55,
      ease: 'power2.out',
      onUpdate: () => {
        displayed.value = Math.round(counter.value)
      },
    })
  },
)
onBeforeUnmount(() => tween?.kill())
</script>
<template>
  <span :aria-label="t('match.chipsAlt', { amount: formatChips(value) })" data-animated-chips>{{
    formatChips(displayed)
  }}</span>
</template>
