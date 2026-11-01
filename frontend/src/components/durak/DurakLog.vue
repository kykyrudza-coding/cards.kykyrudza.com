<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DurakLogEntry } from '../../types/match'
import { glyph } from '../../game/durak/cards'

// A short running commentary of the latest moves, so a card that just flew
// onto the table is never a mystery.
const props = defineProps<{ entries: DurakLogEntry[]; nameOf: (id: number) => string }>()
const { t } = useI18n()

const lines = computed(() =>
  props.entries.slice(-4).map((entry, index) => {
    const text = t(`durak.log.${entry.action}`, {
      name: props.nameOf(entry.user_id),
      cards: entry.cards.map(glyph).join(' '),
    })
    return {
      key: `${entry.round}-${entry.action}-${entry.user_id}-${entry.cards.map(glyph).join('')}-${index}`,
      text: entry.auto ? `${text} ${t('durak.log.timeUp')}` : text,
      action: entry.action,
    }
  }),
)
</script>
<template>
  <TransitionGroup v-if="lines.length" name="log" tag="ol" class="durak-log" aria-live="polite">
    <li v-for="line in lines" :key="line.key" :data-action="line.action">{{ line.text }}</li>
  </TransitionGroup>
</template>
