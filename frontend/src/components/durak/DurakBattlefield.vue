<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { DurakTableSlot, PlayingCard } from '../../types/match'
import { keyOf } from '../../game/durak/cards'
import PlayingCardView from '../game/PlayingCard.vue'

// The cards in play: every attack card sits in its own slot, with the card
// that beat it laid over it. Slots can be tapped, or a card dropped on them.
const props = defineProps<{
  table: DurakTableSlot[]
  /** The viewer is the defender and may beat cards. */
  canDefend: boolean
  /** Attack cards (keys) the picked/dragged card can beat. */
  targets: Set<string>
  /** Attack card (key) the viewer picked as the thing to beat. */
  picked?: string | null
  /** A card is being dragged. */
  dragging: boolean
  taking: boolean
}>()
const emit = defineEmits<{ slot: [index: number]; drop: [index: number]; dropTable: [] }>()
const { t } = useI18n()

const isTarget = (slot: DurakTableSlot) => !slot.defense && props.targets.has(keyOf(slot.attack))
const label = (card: PlayingCard) => `${card.rank} ${card.suit}`
</script>
<template>
  <div
    class="battlefield"
    :class="{ empty: table.length === 0, dragging, taking }"
    @dragover.prevent
    @drop.prevent="emit('dropTable')"
  >
    <p v-if="table.length === 0" class="battlefield-empty">{{ t('durak.table.empty') }}</p>
    <TransitionGroup name="slot" tag="div" class="battlefield-slots">
      <div
        v-for="(slot, index) in table"
        :key="keyOf(slot.attack)"
        class="bf-slot"
        :class="{
          outstanding: !slot.defense,
          beaten: !!slot.defense,
          target: isTarget(slot),
          picked: picked === keyOf(slot.attack),
          clickable: canDefend && !slot.defense,
        }"
        :role="canDefend && !slot.defense ? 'button' : undefined"
        :tabindex="canDefend && !slot.defense ? 0 : undefined"
        :aria-label="canDefend && !slot.defense ? t('durak.table.beat', { card: label(slot.attack) }) : undefined"
        @click="canDefend && !slot.defense && emit('slot', index)"
        @keydown.enter.prevent="canDefend && !slot.defense && emit('slot', index)"
        @dragover.prevent
        @drop.prevent.stop="emit('drop', index)"
      >
        <PlayingCardView :rank="slot.attack.rank" :suit="slot.attack.suit" class="attack-card" />
        <PlayingCardView
          v-if="slot.defense"
          :rank="slot.defense.rank"
          :suit="slot.defense.suit"
          class="defense-card"
        />
      </div>
    </TransitionGroup>
  </div>
</template>
