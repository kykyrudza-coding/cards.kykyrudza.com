<script setup lang="ts">
import type { BlackjackPlayerView } from '../../types/match'
import AnimatedChips from './AnimatedChips.vue'
import SeatIdentity from './SeatIdentity.vue'
import HandView from './HandView.vue'
import { assets } from '../../config/assets'
import { formatChips } from '../../config/gameLabels'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
defineProps<{
  player: BlackjackPlayerView
  isYou: boolean
  active: boolean
  pending?: boolean
  betting?: boolean
  confirmedBet?: number
}>()
</script>
<template>
  <section
    class="player-seat"
    :data-player-id="player.id"
    :class="{ 'own-seat': isYou, 'seat-active': active, 'seat-empty': !player.hands.length }"
    :aria-label="`${player.username ?? t('common.player')}${active ? t('match.seat.currentTurn') : ''}`"
  >
    <div
      v-if="betting && player.status === 'active' && player.chips >= 100"
      class="seat-wager"
      :class="{ 'wager-confirmed': confirmedBet !== undefined }"
      :aria-label="
        confirmedBet === undefined
          ? t('match.bet.confirmYourBet')
          : t('match.bet.confirmedAmount', { amount: formatChips(confirmedBet) })
      "
    >
      <img v-if="confirmedBet !== undefined" :src="assets.chip(100)" alt="" /><span
        v-else
        class="wager-ring"
        aria-hidden="true"
        >+</span
      ><strong v-if="confirmedBet !== undefined">{{ formatChips(confirmedBet) }}</strong>
    </div>
    <SeatIdentity
      :name="player.username ?? t('common.player')"
      :avatar="player.avatar"
      :you-label="isYou ? t('match.seat.you') : undefined"
      :tag="active ? (isYou ? t('match.seat.yourTurn') : t('match.seat.playing')) : undefined"
    >
      <AnimatedChips :value="player.chips" /> <small>{{ t('match.seat.chips') }}</small>
    </SeatIdentity>
    <div class="player-hands">
      <HandView
        v-for="(hand, index) in player.hands"
        :key="index"
        :hand="hand"
        :player-id="player.id"
        :pending="pending"
        :index="index"
        :split="player.hands.length > 1"
      />
    </div>
    <span v-if="player.status === 'out'" class="out-label">{{ t('match.seat.outOfChips') }}</span>
  </section>
</template>
