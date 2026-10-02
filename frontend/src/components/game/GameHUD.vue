<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { BlackjackAction, BlackjackMatchData } from '../../types/match'
import type { PresentationPhase } from '../../game/animations/types'
import { usePreferencesStore } from '../../stores/preferences'
import GameTopBar from './GameTopBar.vue'
import ActionBar from './ActionBar.vue'
import BetControls from './BetControls.vue'
import MachineGunPanel from './MachineGunPanel.vue'
import EventBanner from './EventBanner.vue'
defineProps<{
  match: BlackjackMatchData
  viewerId?: number
  busy: boolean
  connection: string
  lobbyCode?: string
  error?: string | null
  phase: PresentationPhase
  autoError?: string
}>()
defineEmits<{
  action: [action: BlackjackAction]
  settings: []
  leave: []
  refresh: []
  bet: [amount: number]
  machineGun: [mode: 'dealer' | 'player', targetId?: number]
}>()
const preferences = usePreferencesStore()
const { t } = useI18n()
</script>
<template>
  <div class="game-hud">
    <GameTopBar
      :round="match.round"
      :connection="connection"
      :lobby-code="lobbyCode"
      :chips="match.game.players.find((p) => p.id === viewerId)?.chips"
      :bet="
        phase === 'betting'
          ? (match.confirmed_bets?.[String(viewerId)] ?? 0)
          : match.game.players
              .find((p) => p.id === viewerId)
              ?.hands.reduce((sum, h) => sum + h.bet, 0)
      "
      :phase="phase"
      @settings="$emit('settings')"
      @leave="$emit('leave')"
    />
    <div class="game-spacer" />
    <div v-if="error || connection === 'Reconnecting'" class="game-notice" role="status">
      <span>{{ error ?? t('match.connectionInterrupted') }}</span
      ><button type="button" @click="$emit('refresh')">{{ t('match.refresh') }}</button>
    </div>
    <MachineGunPanel
      v-if="match.game.event && match.status === 'active' && phase === 'playing'"
      :event="match.game.event"
      :players="match.game.players"
      :busy="busy"
      @fire="(mode, target) => $emit('machineGun', mode, target)"
    />
    <EventBanner
      v-if="match.game.event_result && (phase === 'settling' || phase === 'collecting')"
      :result="match.game.event_result"
      :players="match.game.players"
    />
    <BetControls
      v-if="phase === 'betting' && match.status === 'active'"
      :match="match"
      :viewer-id="viewerId"
      :busy="busy"
      :error="error || autoError"
      @confirm="$emit('bet', $event)"
    /><ActionBar
      v-else
      :game="match.game"
      :viewer-id="viewerId"
      :busy="busy"
      :finished="match.status !== 'active'"
      :shortcuts="preferences.shortcuts"
      :presentation-phase="phase"
      @action="$emit('action', $event)"
    />
  </div>
</template>
