<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAchievementsStore } from '../../stores/achievements'
import AppIcon from './AppIcon.vue'
import { gameAudio } from '../../audio/GameAudio'
const store = useAchievementsStore()
const { t } = useI18n()
let timer: ReturnType<typeof setTimeout> | null = null
watch(
  () => store.current,
  (achievement) => {
    if (timer) window.clearTimeout(timer)
    if (!achievement) return
    gameAudio.play('win')
    timer = window.setTimeout(() => store.dismissCurrent(), 5000)
  },
)
</script>
<template>
  <Transition name="achievement-splash">
    <div
      v-if="store.current"
      class="achievement-splash"
      role="status"
      aria-live="polite"
      @click="store.dismissCurrent()"
    >
      <span class="achievement-splash-glow" aria-hidden="true" />
      <span class="achievement-splash-icon"><AppIcon :name="(store.current.icon as never)" :size="28" /></span>
      <div class="achievement-splash-copy">
        <span class="achievement-splash-eyebrow">{{ t('achievements.unlocked') }}</span>
        <strong>{{ t(`achievements.catalog.${store.current.key}.name`) }}</strong>
        <span class="achievement-splash-description">{{
          t(`achievements.catalog.${store.current.key}.description`)
        }}</span>
      </div>
    </div>
  </Transition>
</template>
