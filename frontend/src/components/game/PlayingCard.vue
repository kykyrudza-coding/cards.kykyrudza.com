<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { assets } from '../../config/assets'
import { usePreferencesStore } from '../../stores/preferences'
const preferences = usePreferencesStore()
const { t } = useI18n()
withDefaults(
  defineProps<{
    rank?: string
    suit?: string
    hidden?: boolean
    active?: boolean
    inactive?: boolean
    selected?: boolean
    result?: string | null
    cardId?: string
    cardFaceTheme?: string
    cardBackTheme?: string
  }>(),
  {},
)
</script>
<template>
  <span
    class="playing-card"
    :data-card-id="cardId"
    :class="{
      'card-active': active,
      'is-hidden': hidden,
      'card-inactive': inactive,
      'card-selected': selected,
    }"
    :data-result="result"
    role="img"
    :aria-label="
      hidden
        ? t('match.hiddenCard')
        : t('match.cardAlt', { rank, suit: suit ? t(`game.suits.${suit}`) : '' })
    "
    ><span class="card-inner"
      ><span class="card-face card-front"
        ><img
          v-if="!hidden && rank && suit"
          :src="assets.card(rank, suit, cardFaceTheme ?? preferences.cardTheme)"
          alt=""
          draggable="false" /></span
      ><span class="card-face card-back"
        ><img :src="assets.back(cardBackTheme ?? preferences.cardTheme)" alt="" draggable="false" /></span></span
  ></span>
</template>
