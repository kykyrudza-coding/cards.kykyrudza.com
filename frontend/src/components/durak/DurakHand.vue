<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { PlayingCard } from '../../types/match'
import { keyOf, sortHand } from '../../game/durak/cards'
import PlayingCardView from '../game/PlayingCard.vue'

// The viewer's hand: sorted like a real hand (trumps last), fanned to always
// fit the screen, with the cards that can be played right now lit up and the
// rest dimmed. Cards can be tapped or dragged onto the table.
const props = defineProps<{
  cards: PlayingCard[]
  trumpSuit: string
  /** Keys ("rank|suit") of the cards that make a legal move right now. */
  playable: Set<string>
  /** Whether any card can be played (otherwise nothing is dimmed). */
  interactive: boolean
  /** A card picked and waiting for a target. */
  selected?: PlayingCard | null
  /** Key of a card to shake: the player tried something illegal. */
  shaking?: string
}>()
const emit = defineEmits<{
  play: [card: PlayingCard]
  dragstart: [card: PlayingCard]
  dragend: []
}>()

const sorted = computed(() => sortHand(props.cards, props.trumpSuit))

const viewport = ref(typeof window !== 'undefined' ? window.innerWidth : 960)
const update = () => (viewport.value = window.innerWidth)
onMounted(() => window.addEventListener('resize', update))
onBeforeUnmount(() => window.removeEventListener('resize', update))

const cardWidth = computed(() => (viewport.value < 768 ? 60 : 82))
const overlap = computed(() => {
  const n = sorted.value.length
  if (n <= 1) return 0
  const width = cardWidth.value
  const room = Math.min(760, viewport.value - 28)
  const gap = (room - width) / (n - 1)
  return Math.max(0, Math.min(width * 0.78, width - gap))
})

function onDragStart(event: DragEvent, card: PlayingCard) {
  if (!props.playable.has(keyOf(card))) {
    event.preventDefault()
    return
  }
  event.dataTransfer?.setData('text/plain', keyOf(card))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
  emit('dragstart', card)
}
</script>
<template>
  <div class="durak-hand" :style="{ '--card-width': `${cardWidth}px` }" role="group">
    <TransitionGroup name="hand" tag="div" class="hand-row">
      <button
        v-for="(card, index) in sorted"
        :key="keyOf(card)"
        type="button"
        class="hand-card"
        :class="{
          playable: interactive && playable.has(keyOf(card)),
          dim: interactive && !playable.has(keyOf(card)),
          chosen: selected && keyOf(selected) === keyOf(card),
          shake: shaking === keyOf(card),
          trump: card.suit === trumpSuit,
        }"
        :style="index > 0 ? { marginLeft: `-${overlap}px` } : undefined"
        :aria-label="`${card.rank} ${card.suit}`"
        :draggable="interactive && playable.has(keyOf(card))"
        @click="emit('play', card)"
        @dragstart="onDragStart($event, card)"
        @dragend="emit('dragend')"
      >
        <PlayingCardView :rank="card.rank" :suit="card.suit" />
      </button>
    </TransitionGroup>
  </div>
</template>
