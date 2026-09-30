<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { DurakPlayerView } from '../../types/match'
import AppAvatar from '../ui/AppAvatar.vue'
import { assets } from '../../config/assets'
const props = defineProps<{
  player: DurakPlayerView
  isAttacker: boolean
  isDefender: boolean
}>()
const { t } = useI18n()
const roleLabel = () =>
  props.isAttacker
    ? t('durak.seat.attacking')
    : props.isDefender
      ? t('durak.seat.defending')
      : ''
</script>
<template>
  <section
    class="player-seat durak-seat"
    :data-player-id="player.id"
    :class="{
      'seat-active': isAttacker || isDefender,
      'seat-safe': player.status === 'safe',
    }"
    :aria-label="player.username ?? t('common.player')"
  >
    <div class="seat-identity">
      <AppAvatar :name="player.username ?? t('common.player')" />
      <div class="seat-name">
        <strong>{{ player.username ?? t('common.player') }}</strong
        ><span>{{ t('durak.seat.cardCount', { n: player.hand_count }) }}</span>
      </div>
      <span v-if="isAttacker || isDefender" class="turn-label">{{ roleLabel() }}</span>
    </div>
    <div class="durak-hand-back">
      <img
        v-for="index in Math.min(player.hand_count, 6)"
        :key="index"
        class="durak-mini-card"
        :src="assets.back()"
        alt=""
        :style="{ transform: `translateX(${index * -14}px)` }"
      />
    </div>
    <span v-if="player.status === 'safe'" class="out-label">{{ t('durak.seat.safe') }}</span>
  </section>
</template>
