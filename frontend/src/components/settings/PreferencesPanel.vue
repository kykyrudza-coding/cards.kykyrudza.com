<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '../../stores/preferences'
import AppSlider from '../ui/AppSlider.vue'
import AppToggle from '../ui/AppToggle.vue'
import AppSelect from '../ui/AppSelect.vue'
import AppButton from '../ui/AppButton.vue'
import { gameAudio } from '../../audio/GameAudio'
import AppearancePanel from './AppearancePanel.vue'
const { t } = useI18n()
const preferences = usePreferencesStore()
</script>
<template>
  <div class="settings-stack">
    <AppearancePanel />
    <section class="settings-section">
      <h2>{{ t('settings.audio.title') }}</h2>
      <p class="muted small" role="status">
        {{
          t('settings.audio.status', {
            state: t(`settings.audio.state.${gameAudio.status.state}`),
            loaded: gameAudio.status.loaded,
            total: gameAudio.status.total,
            played: gameAudio.status.played,
          })
        }}
      </p>
      <p v-if="gameAudio.status.error" class="error">{{ gameAudio.status.error }}</p>
      <AppButton @click="gameAudio.test()">{{ t('settings.audio.enableTest') }}</AppButton>
      <div class="sound-previews" :aria-label="t('cosmetics.sounds')">
        <AppButton v-for="sound in (['turn','split','double','push','blackjack'] as const)" :key="sound" @click="gameAudio.test(sound)">{{ t(`cosmetics.${sound}`) }}</AppButton>
      </div>
      <AppToggle v-model="preferences.muted" :label="t('settings.audio.muteAll')" /><AppSlider
        v-model="preferences.master"
        :label="t('settings.audio.masterVolume')"
        :disabled="preferences.muted"
      /><AppSlider
        v-model="preferences.sfx"
        :label="t('settings.audio.soundEffects')"
        :disabled="preferences.muted"
      /><AppSlider
        v-model="preferences.voice"
        :label="t('settings.audio.dealerVoice')"
        :disabled="preferences.muted"
      /><AppSlider v-model="preferences.music" :label="t('settings.audio.music')" disabled /><AppToggle
        v-model="preferences.dealerVoice"
        :label="t('settings.audio.dealerVoice')"
        :hint="t('settings.audio.dealerVoiceHint')"
      />
    </section>
    <section class="settings-section">
      <h2>{{ t('settings.graphics.title') }}</h2>
      <AppSelect
        v-model="preferences.quality"
        :label="t('settings.graphics.quality')"
        :options="[
          { value: 'auto', label: t('settings.graphics.auto') },
          { value: 'low', label: t('settings.graphics.low') },
          { value: 'medium', label: t('settings.graphics.medium') },
          { value: 'high', label: t('settings.graphics.high') },
        ]"
      />
      <p class="muted small">{{ t('settings.graphics.note') }}</p>
      <AppToggle
        v-model="preferences.cardAnimations"
        :label="t('settings.graphics.cardAnimations')"
      /><AppToggle
        v-model="preferences.chipAnimations"
        :label="t('settings.graphics.chipAnimations')"
      /><AppToggle
        v-model="preferences.shortcuts"
        :label="t('settings.graphics.shortcuts')"
        :hint="t('settings.graphics.shortcutsHint')"
      />
    </section>
    <section class="settings-section">
      <h2>{{ t('settings.accessibility.title') }}</h2>
      <AppToggle
        v-model="preferences.reducedMotion"
        :label="t('settings.accessibility.reducedMotion')"
        :hint="t('settings.accessibility.reducedMotionHint')"
      />
    </section>
  </div>
</template>

<style scoped>
.sound-previews { display:flex; flex-wrap:wrap; gap:8px; }
</style>
