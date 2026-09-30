<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DurakMatchData, PlayingCard } from '../../types/match'
import { arrangeSeats } from '../../game/shared/seatLayout'
import { useMatchStore } from '../../stores/match'
import DurakPlayerSeat from './DurakPlayerSeat.vue'
import DeckStack from '../game/DeckStack.vue'
import PlayingCardView from '../game/PlayingCard.vue'
import AppButton from '../ui/AppButton.vue'

const props = defineProps<{ match: DurakMatchData; viewerId?: number }>()
const { t } = useI18n()
const store = useMatchStore()

const game = computed(() => props.match.game)
const own = computed(() => game.value.players.find((p) => p.id === props.viewerId))
const seats = computed(() => arrangeSeats(game.value.players, props.viewerId))
const allowed = computed(() => game.value.allowed_actions)
const isDefender = computed(() => game.value.defender_id === props.viewerId)
const outstandingSlots = computed(() => game.value.table.filter((slot) => !slot.defense))

const pendingDefenseTarget = ref<PlayingCard | null>(null)

function cardKey(c: PlayingCard) {
  return `${c.rank}|${c.suit}`
}

// Tapping a card plays it immediately — one tap, one move. Throwing in a
// second matching card is just another tap; there's no multi-select step.
function onHandCardClick(card: PlayingCard) {
  if (store.actionLoading) return

  if (allowed.value.includes('defend')) {
    const target =
      pendingDefenseTarget.value ??
      (outstandingSlots.value.length === 1 ? outstandingSlots.value[0].attack : null)
    if (target) {
      pendingDefenseTarget.value = null
      void store.defend(target, card).catch(() => undefined)
    }
    return
  }

  if (allowed.value.includes('attack')) {
    void store.attack([card]).catch(() => undefined)
  }
}

function selectDefenseTarget(index: number) {
  const slot = game.value.table[index]
  if (!slot || slot.defense !== null || !allowed.value.includes('defend')) return
  pendingDefenseTarget.value = slot.attack
}

async function translate() {
  if (store.actionLoading) return
  await store.translate().catch(() => undefined)
}

async function take() {
  if (store.actionLoading) return
  await store.take().catch(() => undefined)
}

async function pass() {
  if (store.actionLoading) return
  await store.pass().catch(() => undefined)
}

const statusMessage = computed(() => {
  if (game.value.phase === 'finished') return t('durak.status.finished')
  if (isDefender.value && outstandingSlots.value.length > 0) return t('durak.status.yourDefend')
  if (allowed.value.includes('attack') && game.value.table.length === 0)
    return t('durak.status.yourAttack')
  if (allowed.value.includes('attack')) return t('durak.status.yourThrowIn')
  const attacker = game.value.players.find((p) => p.id === game.value.attacker_id)
  const defender = game.value.players.find((p) => p.id === game.value.defender_id)
  return t('durak.status.waitingFor', {
    attacker: attacker?.username ?? t('common.player'),
    defender: defender?.username ?? t('common.player'),
  })
})

const resultMessage = computed(() => {
  if (game.value.phase !== 'finished') return null
  if (game.value.loser_id === null) return t('durak.result.draw')
  if (game.value.loser_id === props.viewerId) return t('durak.result.youLost')
  const loser = game.value.players.find((p) => p.id === game.value.loser_id)
  return t('durak.result.someoneLost', { name: loser?.username ?? t('common.player') })
})

// The hand fans out with just enough overlap to always fit the viewport,
// however many cards are in it (a defender who just took a big throw-in
// can easily be holding a dozen-plus) — cards never grow past the screen
// edge or stack fully hidden behind each other.
const cardWidth = 78
const viewportWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 960)
function updateViewportWidth() {
  viewportWidth.value = window.innerWidth
}
onMounted(() => window.addEventListener('resize', updateViewportWidth))
onBeforeUnmount(() => window.removeEventListener('resize', updateViewportWidth))
const maxHandWidth = computed(() => Math.min(640, viewportWidth.value - 40))
const handOverlap = computed(() => {
  const n = (own.value?.hand ?? []).length
  if (n <= 1) return 0
  const available = maxHandWidth.value - cardWidth
  const perCardGap = available / (n - 1)
  return Math.max(0, Math.min(cardWidth * 0.82, cardWidth - perCardGap))
})
</script>
<template>
  <div class="durak-table">
    <div class="opponent-seats">
      <DurakPlayerSeat
        v-for="seat in seats"
        :key="seat.player.id"
        :player="seat.player"
        :is-attacker="seat.player.id === game.attacker_id"
        :is-defender="seat.player.id === game.defender_id"
        :style="seat.style"
      />
    </div>
    <div class="durak-center">
      <div class="durak-trump-deck">
        <DeckStack />
        <div class="durak-trump-card">
          <PlayingCardView :rank="game.trump_card.rank" :suit="game.trump_card.suit" />
          <span class="durak-deck-count">{{ game.deck_count }}</span>
        </div>
      </div>
      <div class="durak-battlefield" :class="{ empty: game.table.length === 0 }">
        <button
          v-for="(slot, index) in game.table"
          :key="index"
          type="button"
          class="durak-slot"
          :class="{
            outstanding: !slot.defense,
            targeted:
              pendingDefenseTarget !== null && cardKey(pendingDefenseTarget) === cardKey(slot.attack),
          }"
          :disabled="slot.defense !== null || !allowed.includes('defend')"
          @click="selectDefenseTarget(index)"
        >
          <PlayingCardView :rank="slot.attack.rank" :suit="slot.attack.suit" />
          <PlayingCardView
            v-if="slot.defense"
            :rank="slot.defense.rank"
            :suit="slot.defense.suit"
            class="durak-defense-card"
          />
        </button>
      </div>
    </div>
    <div v-if="resultMessage" class="durak-result panel">
      <p>{{ resultMessage }}</p>
    </div>
    <div v-if="own" class="durak-own-seat">
      <div class="durak-own-hand">
        <PlayingCardView
          v-for="(card, index) in own.hand ?? []"
          :key="cardKey(card)"
          :rank="card.rank"
          :suit="card.suit"
          class="durak-hand-card"
          :style="index > 0 ? { marginLeft: `-${handOverlap}px` } : undefined"
          @click="onHandCardClick(card)"
        />
      </div>
      <div class="durak-controls">
        <span class="durak-status" aria-live="polite">{{ statusMessage }}</span>
        <p v-if="store.error" class="error" role="alert">{{ store.error }}</p>
        <div class="durak-buttons">
          <AppButton
            v-if="allowed.includes('translate')"
            variant="secondary"
            :disabled="store.actionLoading"
            @click="translate"
            >{{ t('durak.actions.translate') }}</AppButton
          ><AppButton
            v-if="allowed.includes('take')"
            variant="secondary"
            :disabled="store.actionLoading"
            @click="take"
            >{{ t('durak.actions.take') }}</AppButton
          ><AppButton
            v-if="allowed.includes('pass')"
            variant="primary"
            :disabled="store.actionLoading"
            @click="pass"
            >{{ t('durak.actions.pass') }}</AppButton
          >
        </div>
      </div>
    </div>
  </div>
</template>
