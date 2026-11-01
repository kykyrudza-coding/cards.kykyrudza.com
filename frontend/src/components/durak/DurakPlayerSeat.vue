<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DurakPlayerView } from '../../types/match'
import SeatIdentity from '../game/SeatIdentity.vue'
import { assets } from '../../config/assets'

const props = defineProps<{
  player: DurakPlayerView
  isAttacker: boolean
  isDefender: boolean
  /** One of the two players next to the defender, who may add cards. */
  isThrower: boolean
  passed: boolean
  /** The game is waiting for this player right now. */
  acting: boolean
  taking: boolean
  /** Remaining share (0..1) of the turn clock, only for whoever is acting. */
  timer: number | null
}>()
const { t } = useI18n()

const role = computed(() => {
  if (props.player.status === 'safe') return 'safe'
  if (props.isDefender) return props.taking ? 'taking' : 'defending'
  if (props.isAttacker) return 'attacking'
  if (props.isThrower) return props.passed ? 'passed' : 'throwing'
  return ''
})
const tag = computed(() => (role.value ? t(`durak.seat.${role.value}`) : undefined))
const backs = computed(() => Math.min(props.player.hand_count, 8))
</script>
<template>
  <section
    class="player-seat durak-seat"
    :data-player-id="player.id"
    :data-role="role || undefined"
    :class="{
      'seat-active': acting,
      'seat-safe': player.status === 'safe',
      'seat-low': timer !== null && timer < 0.25,
    }"
    :style="timer !== null ? { '--timer': Math.max(0, timer) } : undefined"
    :aria-label="player.username ?? t('common.player')"
  >
    <SeatIdentity
      :name="player.username ?? t('common.player')"
      :avatar="player.avatar"
      :tag="tag"
      :dot="acting"
      >{{ t('durak.seat.cardCount', { n: player.hand_count }) }}</SeatIdentity
    >
    <div class="durak-hand-back" aria-hidden="true">
      <img
        v-for="index in backs"
        :key="index"
        class="durak-mini-card"
        :src="assets.back()"
        alt=""
        :style="{ transform: `translateX(${index * -14}px) rotate(${(index - backs / 2) * 2.2}deg)` }"
      />
      <span v-if="player.hand_count > 8" class="hand-more">+{{ player.hand_count - 8 }}</span>
    </div>
    <span v-if="player.status === 'safe'" class="out-label">{{ t('durak.seat.safe') }}</span>
  </section>
</template>
