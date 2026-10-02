<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { BlackjackPlayerView, MachineGunEvent } from '../../types/match'
import MachineGunIcon from './MachineGunIcon.vue'
import AppButton from '../ui/AppButton.vue'
defineProps<{
  event: MachineGunEvent
  players: BlackjackPlayerView[]
  busy: boolean
}>()
defineEmits<{ fire: [mode: 'dealer' | 'player', targetId?: number] }>()
const { t } = useI18n()
const choosing = ref(false)
</script>
<template>
  <section class="machine-gun-panel" role="alertdialog" :aria-label="t('events.machineGun.title')">
    <MachineGunIcon class="machine-gun-art" />
    <header>
      <strong>{{ t('events.machineGun.title') }}</strong>
      <span>{{ t('events.machineGun.subtitle') }}</span>
    </header>
    <div v-if="!choosing" class="machine-gun-options">
      <AppButton variant="danger" :disabled="busy" @click="$emit('fire', 'dealer')">{{
        t('events.machineGun.dealer')
      }}</AppButton>
      <small>{{ t('events.machineGun.dealerHint') }}</small>
      <AppButton
        variant="danger"
        :disabled="busy || event.targets.length === 0"
        @click="choosing = true"
        >{{ t('events.machineGun.player') }}</AppButton
      >
      <small>{{ t('events.machineGun.playerHint') }}</small>
    </div>
    <div v-else class="machine-gun-options">
      <AppButton
        v-for="id in event.targets"
        :key="id"
        variant="danger"
        :disabled="busy"
        @click="$emit('fire', 'player', id)"
        >{{ players.find((p) => p.id === id)?.username ?? t('common.player') }}</AppButton
      >
      <AppButton variant="ghost" @click="choosing = false">{{
        t('events.machineGun.back')
      }}</AppButton>
    </div>
  </section>
</template>
