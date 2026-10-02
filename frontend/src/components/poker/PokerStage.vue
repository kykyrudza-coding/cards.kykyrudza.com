<script setup lang="ts">
import PokerTable from './PokerTable.vue'
import type { PokerMatchData } from '../../types/match'
withDefaults(
  defineProps<{
    tableSkin?: string
    match?: PokerMatchData | null
    viewerId?: number
    blocked?: boolean
  }>(),
  { tableSkin: 'classic' },
)
</script>
<template>
  <div class="game-stage">
    <div class="stage-light" aria-hidden="true" />
    <div class="stage-table" :data-skin="tableSkin" aria-hidden="true">
      <div class="table-felt"><div class="felt-stitch" /></div>
    </div>
    <PokerTable v-if="match" :match="match" :viewer-id="viewerId" :blocked="blocked" />
  </div>
</template>
