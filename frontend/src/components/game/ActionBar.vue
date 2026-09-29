<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PresentationPhase } from '../../game/animations/types'
import type { BlackjackAction, BlackjackGameState } from '../../types/match'
import AppButton from '../ui/AppButton.vue'
const props = defineProps<{
  game: BlackjackGameState
  viewerId?: number
  busy: boolean
  finished: boolean
  shortcuts: boolean
  presentationPhase?: PresentationPhase
}>()
defineEmits<{ action: [action: BlackjackAction] }>()
const { t } = useI18n()
const actions: BlackjackAction[] = ['hit', 'stand', 'double', 'split']
const myTurn = computed(
  () =>
    !props.finished &&
    props.game.phase === 'player_turn' &&
    props.game.current_player_id === props.viewerId,
)
const message = computed(() =>
  props.presentationPhase === 'dealing'
    ? t('match.actionBar.dealing')
    : props.presentationPhase === 'dealer'
      ? t('match.actionBar.dealerPlaying')
      : props.presentationPhase === 'settling'
        ? t('match.actionBar.settling')
        : props.presentationPhase === 'collecting'
          ? t('match.actionBar.preparingNext')
          : props.finished
            ? t('match.actionBar.matchComplete')
            : props.game.phase === 'round_finished'
              ? t('match.actionBar.roundComplete')
              : props.game.phase === 'dealer_turn'
                ? t('match.actionBar.dealerTurn')
                : myTurn.value
                  ? t('match.actionBar.yourTurn')
                  : props.game.phase === 'player_turn'
                    ? t('match.actionBar.waitingFor', {
                        name:
                          props.game.players.find((p) => p.id === props.game.current_player_id)
                            ?.username ?? t('match.actionBar.waitingForAnother'),
                      })
                    : t('match.actionBar.dealingNext'),
)
const keys: Partial<Record<BlackjackAction, string>> = {
  hit: 'H',
  stand: 'S',
  double: 'D',
  split: 'P',
}
</script>
<template>
  <footer class="action-bar">
    <div class="action-status" aria-live="polite">
      <span v-if="myTurn" class="status-dot" />{{ message }}
    </div>
    <div class="action-buttons">
      <AppButton
        v-for="action in actions"
        :key="action"
        :variant="action === 'hit' ? 'primary' : 'secondary'"
        :aria-label="t(`game.actions.${action}`)"
        :disabled="!myTurn || !game.allowed_actions.includes(action) || busy"
        :aria-keyshortcuts="shortcuts ? keys[action] : undefined"
        @click="$emit('action', action)"
        >{{ t(`game.actions.${action}`)
        }}<kbd
          v-if="keys[action]"
          :title="
            shortcuts ? t('match.actionBar.shortcutEnabled') : t('match.actionBar.shortcutDisabled')
          "
          >{{ keys[action] }}</kbd
        ></AppButton
      >
    </div>
    <span class="action-footnote">{{ t('match.actionBar.footnote') }}</span>
  </footer>
</template>
