<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { PokerMatchData } from '../../types/match'
import AppIcon from '../ui/AppIcon.vue'
import AppButton from '../ui/AppButton.vue'
defineProps<{
  match: PokerMatchData
  connection: string
  lobbyCode?: string
  error?: string | null
}>()
defineEmits<{ settings: []; leave: []; refresh: [] }>()
const { t } = useI18n()
</script>
<template>
  <div class="game-hud">
    <header class="game-topbar">
      <div class="game-brand">
        <AppIcon name="cards" :size="25" />
        <div>
          <strong>{{ t('lobby.poker') }}</strong><span>{{ t('match.roundLabel', { n: match.round }) }}</span>
        </div>
      </div>
      <span class="connection" :class="{ offline: connection !== 'Connected' }"
        ><AppIcon :name="connection === 'Connected' ? 'connected' : 'disconnected'" :size="14" />{{
          t(`common.connection.${connection}`)
        }}</span
      >
      <div class="game-topbar-actions">
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
    <div class="game-spacer" />
    <div v-if="error || connection === 'Reconnecting'" class="game-notice" role="status">
      <span>{{ error ?? t('match.connectionInterrupted') }}</span
      ><button type="button" @click="$emit('refresh')">{{ t('match.refresh') }}</button>
    </div>
  </div>
</template>
