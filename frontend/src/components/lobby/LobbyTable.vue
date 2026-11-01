<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { GameType, LobbyPlayer } from '../../types/lobby'
import AppAvatar from '../ui/AppAvatar.vue'
import AppIcon from '../ui/AppIcon.vue'

// A small top-down table: who has sat down, who is ready, which seats are open.
const props = defineProps<{ players: LobbyPlayer[]; max: number; game: GameType }>()
const { t } = useI18n()
const gameIcon = { blackjack: 'blackjack', durak: 'swords', poker: 'chip' } as const
const seats = computed(() =>
  Array.from({ length: props.max }, (_, index) => {
    const angle = Math.PI / 2 + (index / props.max) * Math.PI * 2
    return {
      player: props.players[index] ?? null,
      style: {
        left: `${50 + 45 * Math.cos(angle)}%`,
        top: `${50 + 44 * Math.sin(angle)}%`,
      },
    }
  }),
)
</script>
<template>
  <div class="lobby-table" role="img" :aria-label="t('lobby.atTable')">
    <div class="lobby-felt">
      <AppIcon :name="gameIcon[game]" :size="30" />
    </div>
    <div
      v-for="(seat, index) in seats"
      :key="index"
      class="lobby-seat"
      :class="{ 'is-ready': seat.player?.is_ready, 'is-open': !seat.player }"
      :style="seat.style"
    >
      <AppAvatar v-if="seat.player" :name="seat.player.username" :src="seat.player.avatar" />
      <span v-else class="avatar"><AppIcon name="plus" :size="16" /></span>
      <span v-if="seat.player?.is_ready" class="seat-ready"><AppIcon name="check" :size="11" /></span>
    </div>
  </div>
</template>
