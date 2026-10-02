<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PokerPlayerView } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import { pokerMotionKey } from './motion'
import AppAvatar from '../ui/AppAvatar.vue'
import PlayingCardView from '../game/PlayingCard.vue'

const props = defineProps<{
  player: PokerPlayerView
  round: number
  /** Position in the deal order (0 = first card goes to the small blind). */
  dealOrder: number
  seatCount: number
  isCurrent: boolean
  winAmount: number
}>()
const { t } = useI18n()
const motion = inject(pokerMotionKey)

// Standard poker table abbreviations — the same in every language.
const badges = computed(() =>
  [
    props.player.is_dealer && 'D',
    props.player.is_small_blind && 'SB',
    props.player.is_big_blind && 'BB',
  ].filter((badge): badge is string => Boolean(badge)),
)
const statusLabel = computed(() =>
  props.player.status === 'folded'
    ? t('poker.seat.folded')
    : props.player.status === 'all_in'
      ? t('poker.seat.allIn')
      : props.player.status === 'out'
        ? t('poker.seat.out')
        : '',
)
// Face-down until a showdown reveals the real cards (those get a flip).
const cards = computed(() =>
  Array.from({ length: props.player.hand_count }, (_, index) => ({
    index,
    face: props.player.hand?.[index] ?? null,
  })),
)
</script>
<template>
  <section
    class="player-seat poker-seat"
    :data-player-id="player.id"
    :class="{
      'seat-active': isCurrent,
      'poker-winner': winAmount > 0,
      'poker-folded': player.status === 'folded' || player.status === 'out',
    }"
    :aria-label="player.username ?? t('common.player')"
  >
    <TransitionGroup
      tag="div"
      class="poker-seat-cards"
      :css="false"
      aria-hidden="true"
      @enter="motion?.enterCard"
      @leave="motion?.leaveInstantly"
    >
      <PlayingCardView
        v-for="card in cards"
        :key="`${round}-${card.index}-${card.face ? 'f' : 'b'}`"
        :rank="card.face?.rank"
        :suit="card.face?.suit"
        :hidden="!card.face"
        :data-order="card.face ? card.index : card.index * seatCount + dealOrder"
        :data-reveal="card.face ? '1' : '0'"
      />
    </TransitionGroup>
    <div class="seat-identity">
      <AppAvatar :name="player.username ?? t('common.player')" />
      <div class="seat-name">
        <strong>{{ player.username ?? t('common.player') }}</strong
        ><span>{{ formatChips(player.chips) }}</span>
      </div>
      <span v-if="isCurrent" class="poker-turn-dot" aria-hidden="true" />
    </div>
    <div class="poker-seat-meta">
      <span v-for="badge in badges" :key="badge" class="poker-badge">{{ badge }}</span>
      <span v-if="statusLabel" class="poker-status">{{ statusLabel }}</span>
    </div>
    <TransitionGroup
      tag="div"
      class="poker-bet-slot"
      :css="false"
      @enter="motion?.betIn"
      @leave="motion?.leaveInstantly"
    >
      <span v-if="player.bet > 0" :key="player.bet" class="poker-bet" :data-bet-for="player.id">{{
        formatChips(player.bet)
      }}</span>
    </TransitionGroup>
    <span v-if="winAmount > 0" class="poker-win-float" aria-hidden="true"
      >+{{ formatChips(winAmount) }}</span
    >
  </section>
</template>
