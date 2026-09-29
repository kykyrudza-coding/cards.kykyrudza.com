<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { LobbyPlayer } from '../../types/lobby'
import AppAvatar from '../ui/AppAvatar.vue'
import AppBadge from '../ui/AppBadge.vue'
import AppIcon from '../ui/AppIcon.vue'
defineProps<{ player?: LobbyPlayer; isYou?: boolean }>()
const { t } = useI18n()
</script>
<template>
  <article class="lobby-player" :class="{ 'is-ready': player?.is_ready, 'is-empty': !player }">
    <template v-if="player"
      ><AppAvatar :name="player.username" />
      <div class="player-info">
        <strong
          >{{ player.username }}
          <span v-if="isYou" class="muted small">{{ t('lobby.playerCard.you') }}</span></strong
        ><small :class="{ accent: player.is_ready }"
          ><AppIcon v-if="player.is_ready" name="check" :size="13" />{{
            player.is_ready ? t('lobby.playerCard.readyToPlay') : t('lobby.playerCard.notReady')
          }}</small
        >
      </div>
      <AppBadge v-if="player.is_host" tone="warning">{{ t('lobby.playerCard.host') }}</AppBadge></template
    ><template v-else
      ><span class="avatar"><AppIcon name="plus" /></span>
      <div>
        <strong>{{ t('lobby.playerCard.openSeat') }}</strong
        ><small>{{ t('lobby.playerCard.waitingForPlayer') }}</small>
      </div></template
    >
  </article>
</template>
