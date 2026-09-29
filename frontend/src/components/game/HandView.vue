<script setup lang="ts">
import type { BlackjackHandView } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import { useI18n } from 'vue-i18n'
const { t } = useI18n()
import { assets } from '../../config/assets'
import PlayingCard from './PlayingCard.vue'
defineProps<{
  hand: BlackjackHandView
  index: number
  playerId: number
  split?: boolean
  pending?: boolean
}>()
const profitLabel = (value: number) =>
  value === 0 ? '±0' : `${value > 0 ? '+' : ''}${formatChips(value)}`
</script>
<template>
  <div
    class="hand-view"
    :data-hand-result="hand.result || undefined"
    :data-player-id="playerId"
    :class="{
      'active-hand': hand.is_active && !pending,
      'inactive-hand': split && !hand.is_active && !hand.result,
      'split-hand': split,
      'hand-bust': hand.status === 'bust',
      [`hand-result-${hand.result}`]: !!hand.result,
    }"
  >
    <span v-if="split" class="hand-index">{{ t('match.hand.number', { n: index + 1 }) }}</span>
    <div class="hand-cards" :style="{ '--cards-count': hand.cards.length }">
      <PlayingCard
        v-for="(card, i) in hand.cards"
        :key="i"
        :rank="card.rank"
        :suit="card.suit"
        :active="hand.is_active"
        :result="hand.result"
        :card-id="`p${playerId}-h${index}-c${i}`"
      />
    </div>
    <div v-if="hand.cards.length" class="hand-details">
      <strong class="score-badge">{{ pending ? '—' : hand.score }}</strong
      ><span class="hand-bet"
        ><img data-bet-chip :src="assets.chip(25)" alt="" />{{
          t('match.hand.bet', { amount: formatChips(hand.bet) })
        }}</span
      ><span
        v-if="!pending && !hand.result && hand.status !== 'playing'"
        class="hand-status"
        :class="`result-${hand.status}`"
        >{{ t(`game.status.${hand.status}`) }}</span
      >
    </div>
    <div v-if="hand.result" class="inline-result" :class="`result-${hand.result}`" role="status">
      <strong>{{ t(`game.status.${hand.result}`) }}</strong
      ><span v-if="hand.profit !== undefined && hand.profit !== null">{{
        profitLabel(hand.profit)
      }}</span>
    </div>
  </div>
</template>
