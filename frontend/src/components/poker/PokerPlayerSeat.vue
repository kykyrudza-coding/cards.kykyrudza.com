<script setup lang="ts">
import { computed, inject } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PokerPlayerView } from '../../types/match'
import { formatChips } from '../../config/gameLabels'
import { pokerMotionKey } from './motion'
import SeatIdentity from '../game/SeatIdentity.vue'
import PlayingCardView from '../game/PlayingCard.vue'
import ChipPile from '../game/ChipPile.vue'
import WinBurst from '../game/WinBurst.vue'

const props = defineProps<{
  player: PokerPlayerView
  round: number
  /** Position in the deal order (0 = first card goes to the small blind). */
  dealOrder: number
  seatCount: number
  isCurrent: boolean
  winAmount: number
  /** Remaining share (0..1) of the turn clock, only for the player to act. */
  timer: number | null
  /** Card keys ("rank|suit") that make up a winning hand, once announced. */
  winningKeys: Set<string>
  /** Whether the showdown result has been announced (names, winners, dimming). */
  announced: boolean
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
// Blinds are shown by the badges and the chips in front of the seat.
const lastAction = computed(() => {
  const { last_action: action, last_amount: amount } = props.player
  if (!action || action === 'small_blind' || action === 'big_blind' || action === 'fold') return null
  if (action === 'all_in') return null // the status label already says so
  return {
    key: `${action}:${amount}`,
    tone: action,
    text: t(`poker.last.${action}`, { n: formatChips(amount) }),
  }
})
// Face-down until a showdown reveals the real cards (those get a flip).
const cards = computed(() =>
  Array.from({ length: props.player.hand_count }, (_, index) => ({
    index,
    face: props.player.hand?.[index] ?? null,
  })),
)
const isWinning = (card: { rank: string; suit: string } | null) =>
  card !== null && props.winningKeys.has(`${card.rank}|${card.suit}`)
const handName = computed(() =>
  props.announced && props.player.hand_name ? t(`poker.hands.${props.player.hand_name}`) : '',
)
</script>
<template>
  <section
    class="player-seat poker-seat"
    :data-player-id="player.id"
    :class="{
      'seat-active': isCurrent,
      'poker-winner': winAmount > 0 && announced,
      'poker-folded': player.status === 'folded' || player.status === 'out',
    }"
    :style="timer !== null ? { '--timer': Math.max(0, timer) } : undefined"
    :data-low="timer !== null && timer < 0.25 ? '' : undefined"
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
        :class="{
          'poker-card-win': announced && isWinning(card.face),
          'poker-card-dim': announced && card.face && winningKeys.size > 0 && !isWinning(card.face),
        }"
        :data-order="card.face ? card.index : card.index * seatCount + dealOrder"
        :data-reveal="card.face ? '1' : '0'"
      />
    </TransitionGroup>
    <SeatIdentity
      :name="player.username ?? t('common.player')"
      :avatar="player.avatar"
      :dot="isCurrent"
      >{{ formatChips(player.chips) }}</SeatIdentity
    >
    <div v-if="timer !== null" class="poker-timer" :class="{ low: timer < 0.25 }" aria-hidden="true">
      <span :style="{ width: `${Math.max(0, timer) * 100}%` }" />
    </div>
    <div class="poker-seat-meta">
      <span v-for="badge in badges" :key="badge" class="poker-badge" :data-badge="badge">{{
        badge
      }}</span>
      <span v-if="statusLabel" class="poker-status">{{ statusLabel }}</span>
      <Transition name="poker-pop">
        <span v-if="lastAction" :key="lastAction.key" class="poker-last" :data-tone="lastAction.tone">{{
          lastAction.text
        }}</span>
      </Transition>
    </div>
    <span v-if="handName" class="poker-handname">{{ handName }}</span>
    <TransitionGroup
      tag="div"
      class="poker-bet-slot"
      :css="false"
      @enter="motion?.betIn"
      @leave="motion?.leaveInstantly"
    >
      <span v-if="player.bet > 0" :key="player.bet" class="poker-bet" :data-bet-for="player.id"
        ><ChipPile :amount="player.bet" :max-height="4" />{{ formatChips(player.bet) }}</span
      >
    </TransitionGroup>
    <WinBurst v-if="winAmount > 0 && announced" />
    <span v-if="winAmount > 0 && announced" class="poker-win-float" aria-hidden="true"
      >+{{ formatChips(winAmount) }}</span
    >
  </section>
</template>
