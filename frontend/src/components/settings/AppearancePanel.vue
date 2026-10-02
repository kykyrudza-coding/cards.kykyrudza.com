<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '../../stores/preferences'
import { preloadCardImages } from '../../game/cardImages'
import { assets } from '../../config/assets'
import AppSelect from '../ui/AppSelect.vue'
const { t } = useI18n()
const preferences = usePreferencesStore()
const loading = ref(false)
async function selectDeck(theme: string) {
  loading.value = true
  try {
    await preloadCardImages(theme)
    preferences.cardTheme = theme
  } finally { loading.value = false }
}
</script>
<template>
  <section class="settings-section appearance-panel">
    <h2>{{ t('cosmetics.title') }}</h2>
    <AppSelect v-model="preferences.tableSkin" :label="t('cosmetics.table')" :options="[
      { value: 'classic', label: t('cosmetics.classic') },
      { value: 'midnight', label: t('cosmetics.midnight') },
    ]" />
    <img class="table-style-preview" :src="assets.table(preferences.tableSkin)" :alt="t('cosmetics.table')" />
    <AppSelect :model-value="preferences.cardTheme" @update:model-value="selectDeck" :disabled="loading" :label="t('cosmetics.cards')" :options="[
      { value: 'classic', label: t('cosmetics.classic') },
      { value: 'midnight', label: t('cosmetics.midnight') },
    ]" />
    <div class="deck-style-preview" :aria-busy="loading">
      <img :src="assets.card('A','S',preferences.cardTheme)" :alt="t('cosmetics.cards')" />
      <img :src="assets.card('K','H',preferences.cardTheme)" alt="" />
      <img :src="assets.back(preferences.cardTheme)" alt="" />
    </div>
  </section>
</template>
<style scoped>
.table-style-preview { width: 100%; height: 100px; object-fit: cover; border-radius: 16px; }
.deck-style-preview { display:flex; gap:12px; justify-content:center; padding:12px; background:#0d1421; border-radius:12px; }
.deck-style-preview img { width: min(25%,72px); height:auto; filter:drop-shadow(0 4px 5px #0006); }
</style>
