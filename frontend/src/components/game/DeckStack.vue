<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { assets } from '../../config/assets'
import { usePreferencesStore } from '../../stores/preferences'
const preferences = usePreferencesStore()
const { t } = useI18n()
</script>
<template>
  <div class="deck-area" :aria-label="t('match.deckAria')">
    <div class="deck-stack" data-deck>
      <img
        v-for="index in 4"
        :key="index"
        :src="assets.back(preferences.cardTheme)"
        alt=""
        :style="{ transform: `translate3d(${index * 1.5}px,${-index * 2}px,${index}px)` }"
      />
    </div>
    <div class="discard-pile" data-discard aria-hidden="true"><span>{{ t('match.discard') }}</span></div>
  </div>
</template>
