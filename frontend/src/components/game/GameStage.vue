<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import GameTable from './GameTable.vue'
import type { MatchData } from '../../types/match'
import type { PresentationPhase } from '../../game/animations/types'
withDefaults(
  defineProps<{
    tableSkin?: string
    match?: MatchData | null
    phase: PresentationPhase
    viewerId?: number
  }>(),
  { tableSkin: 'classic' },
)
const { t } = useI18n()
</script>
<template>
  <div class="game-stage">
    <div class="stage-light" aria-hidden="true" />
    <div class="stage-table" :data-skin="tableSkin" aria-hidden="true">
      <div class="table-felt">
        <div class="felt-stitch" />
        <div class="table-wordmark">
          <span class="table-emblem">♠</span>{{ t('match.tableWordmark') }}<span class="table-rule">{{
            t('match.tablePays')
          }}</span
          ><span class="table-house">{{ t('match.tableHouse') }}</span>
        </div>
      </div>
    </div>
    <GameTable v-if="match" :match="match" :phase="phase" :viewer-id="viewerId" />
  </div>
</template>
