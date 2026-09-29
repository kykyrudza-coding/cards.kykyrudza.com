<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import AppIcon from '../ui/AppIcon.vue'
import AppButton from '../ui/AppButton.vue'
import { formatChips } from '../../config/gameLabels'
import { gameAudio } from '../../audio/GameAudio'
import { usePreferencesStore } from '../../stores/preferences'
const { t } = useI18n()
const preferences = usePreferencesStore()
function sound() {
  if (gameAudio.status.state !== 'ready') {
    void gameAudio.test()
    return
  }
  preferences.muted = !preferences.muted
}
defineProps<{
  round: number
  connection: string
  lobbyCode?: string
  chips?: number
  bet?: number
  phase?: string
}>()
defineEmits<{ settings: []; leave: [] }>()
</script>
<template>
  <header class="game-topbar">
    <div class="game-brand">
      <AppIcon name="cards" :size="25" />
      <div>
        <strong>{{ t('lobby.blackjack') }}</strong><span>{{ t('match.roundLabel', { n: round }) }}</span>
      </div>
    </div>
    <span class="connection" :class="{ offline: connection !== 'Connected' }"
      ><AppIcon :name="connection === 'Connected' ? 'connected' : 'disconnected'" :size="14" />{{
        t(`common.connection.${connection}`)
      }}</span
    >
    <div class="table-info">
      <span
        >{{ t('match.hud.balance') }}<strong>{{ formatChips(chips ?? 0) }}</strong></span
      ><span
        >{{ t('match.hud.bet') }}<strong>{{ formatChips(bet ?? 0) }}</strong></span
      ><span class="table-info-phase"
        >{{ t('match.hud.stage') }}<strong>{{
          phase === 'betting' ? t('match.hud.confirmBet') : phase
        }}</strong></span
      >
    </div>
    <div class="game-topbar-actions">
      <button
        type="button"
        class="icon-button"
        :aria-label="
          gameAudio.status.state !== 'ready'
            ? t('match.hud.enableSound')
            : preferences.muted
              ? t('match.hud.unmuteSound')
              : t('match.hud.muteSound')
        "
        :aria-pressed="preferences.muted"
        :title="
          gameAudio.status.state !== 'ready'
            ? t('match.hud.tapToEnable')
            : preferences.muted
              ? t('match.hud.soundMuted')
              : t('match.hud.soundOn')
        "
        @pointerdown.stop
        @click="sound"
      >
        <AppIcon name="volume" :style="{ opacity: preferences.muted ? 0.35 : 1 }" />
      </button>
      <span v-if="lobbyCode" class="muted small lobby-reference">{{
        t('match.hud.lobbyLabel', { code: lobbyCode })
      }}</span
      ><button
        type="button"
        class="icon-button"
        :aria-label="t('match.hud.gameSettings')"
        @click="$emit('settings')"
      >
        <AppIcon name="settings" /></button
      ><AppButton variant="ghost" @click="$emit('leave')"
        ><AppIcon name="logout" :size="17" /><span>{{ t('match.hud.leave') }}</span></AppButton
      >
    </div>
  </header>
</template>
