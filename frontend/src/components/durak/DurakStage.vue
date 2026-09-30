<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DurakTable from './DurakTable.vue'
import type { DurakMatchData } from '../../types/match'
withDefaults(
  defineProps<{
    tableSkin?: string
    match?: DurakMatchData | null
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
          <span class="table-emblem">♠</span>{{ t('durak.tableWordmark') }}
        </div>
      </div>
    </div>
    <DurakTable v-if="match" :match="match" :viewer-id="viewerId" />
  </div>
</template>
