<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { Lobby } from '../../types/lobby'
import { formatChips } from '../../config/gameLabels'
import AppIcon from '../ui/AppIcon.vue'
defineProps<{ lobby: Lobby }>()
const { t } = useI18n()
</script>
<template>
  <section class="panel lobby-settings">
    <div class="section-heading">
      <span class="section-icon"><AppIcon name="settings" /></span>
      <h2>{{ t('lobby.settings.title') }}</h2>
    </div>
    <dl>
      <div>
        <dt>{{ t('lobby.settings.game') }}</dt>
        <dd>{{ t('lobby.blackjack') }}</dd>
      </div>
      <div>
        <dt>{{ t('lobby.settings.startingChips') }}</dt>
        <dd>{{ formatChips(lobby.starting_chips) }}</dd>
      </div>
      <div>
        <dt>{{ t('lobby.settings.defaultBet') }}</dt>
        <dd>{{ formatChips(lobby.default_bet) }}</dd>
      </div>
      <div>
        <dt>{{ t('lobby.settings.players') }}</dt>
        <dd>{{ lobby.players.length }} / {{ lobby.max_players }}</dd>
      </div>
      <div>
        <dt>{{ t('lobby.settings.room') }}</dt>
        <dd>{{ lobby.is_private ? t('lobby.settings.private') : t('lobby.settings.public') }}</dd>
      </div>
    </dl>
    <p class="muted small">
      {{ t('lobby.settings.footer') }}<br />{{ t('lobby.settings.footerLine2') }}
    </p>
  </section>
</template>
