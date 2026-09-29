<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MatchData } from '../../types/match'
import type { PresentationPhase } from '../../game/animations/types'
import { arrangeSeats } from '../../game/shared/seatLayout'
import DealerArea from './DealerArea.vue'
import PlayerSeat from './PlayerSeat.vue'
import ChipStack from './ChipStack.vue'
import DeckStack from './DeckStack.vue'
const props = defineProps<{ match: MatchData; viewerId?: number; phase: PresentationPhase }>()
const { t } = useI18n()
const seats = computed(() => arrangeSeats(props.match.game.players, props.viewerId))
const own = computed(() => props.match.game.players.find((p) => p.id === props.viewerId))
const pending = computed(() => props.phase === 'dealing')
const totalBet = computed(() =>
  props.phase === 'betting'
    ? 0
    : props.match.game.players.reduce(
        (sum, p) => sum + p.hands.reduce((total, h) => total + h.bet, 0),
        0,
      ),
)
</script>
<template>
  <div class="game-playfield" :class="{ 'many-seats': seats.length > 4 }" :data-phase="phase">
    <DealerArea :dealer="match.game.dealer" :playing="phase === 'dealer'" /><DeckStack />
    <div class="opponent-seats">
      <PlayerSeat
        v-for="seat in seats"
        :key="seat.player.id"
        :player="seat.player"
        :betting="phase === 'betting'"
        :confirmed-bet="match.confirmed_bets?.[String(seat.player.id)]"
        :is-you="false"
        :pending="pending"
        :active="
          phase === 'playing' &&
          match.status === 'active' &&
          seat.player.id === match.game.current_player_id
        "
        :style="seat.style"
      />
    </div>
    <div v-if="totalBet > 0" class="table-bet">
      <ChipStack :amount="totalBet" :label="t('match.totalBet')" />
    </div>
    <PlayerSeat
      v-if="own"
      class="current-user-seat"
      :player="own"
      :betting="phase === 'betting'"
      :confirmed-bet="match.confirmed_bets?.[String(own.id)]"
      is-you
      :pending="pending"
      :active="
        phase === 'playing' && match.status === 'active' && own.id === match.game.current_player_id
      "
    />
  </div>
</template>
