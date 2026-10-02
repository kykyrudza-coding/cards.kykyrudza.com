<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import MachineGunIcon from './MachineGunIcon.vue'
import type { BlackjackPlayerView, MachineGunResult } from '../../types/match'
const props = defineProps<{ result: MachineGunResult; players: BlackjackPlayerView[] }>()
const { t } = useI18n()
const name = (id: number | null) =>
  props.players.find((p) => p.id === id)?.username ?? t('events.machineGun.someone')
const message = computed(() =>
  props.result.mode === 'dealer'
    ? t('events.machineGun.resultDealer', { holder: name(props.result.holder_id) })
    : t('events.machineGun.resultPlayer', {
        holder: name(props.result.holder_id),
        target: name(props.result.target_id),
      }),
)
</script>
<template>
  <div class="event-fire" aria-hidden="true"><i /><i /><i /></div>
  <div class="event-banner" role="status">
    <MachineGunIcon class="event-banner-icon" />{{ message }}
  </div>
</template>
