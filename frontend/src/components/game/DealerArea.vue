<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { BlackjackGameState } from '../../types/match'
import PlayingCard from './PlayingCard.vue'
defineProps<{ dealer: BlackjackGameState['dealer']; playing: boolean }>()
const { t } = useI18n()
</script>
<template>
  <section class="dealer-area" :aria-label="t('match.dealer.handAria')">
    <div class="dealer-label">{{ t('match.dealer.label') }} <span v-if="playing" class="status-dot" /></div>
    <div class="dealer-cards">
      <PlayingCard
        v-for="(card, index) in dealer.cards"
        :key="index"
        :card-id="`dealer-${index}`"
        :hidden="'hidden' in card"
        :rank="'rank' in card ? card.rank : undefined"
        :suit="'suit' in card ? card.suit : undefined"
      />
    </div>
    <span v-if="dealer.score !== null" class="dealer-score"
      >{{ t('match.dealer.label') }} <strong>{{ dealer.score }}</strong></span
    ><span v-else-if="dealer.cards.length" class="dealer-score muted">{{
      t('match.dealer.holeHidden')
    }}</span>
    <span
      v-if="dealer.status === 'bust' || dealer.status === 'blackjack'"
      class="hand-status"
      :class="`result-${dealer.status}`"
      >{{ t(`game.status.${dealer.status}`) }}</span
    >
  </section>
</template>
