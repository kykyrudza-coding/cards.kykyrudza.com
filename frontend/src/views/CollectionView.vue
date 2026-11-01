<script setup lang="ts">
import { computed, ref } from 'vue'
import { usePreferencesStore } from '../stores/preferences'
import { preloadCardImages } from '../game/cardImages'
import { useI18n } from 'vue-i18n'
import { assets } from '../config/assets'
import AppTabs from '../components/ui/AppTabs.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const preferences = usePreferencesStore()
const tab = ref('backs')
const decks = ['classic', 'midnight', 'fourcolor'] as const
const tables = ['classic', 'midnight'] as const
const loadingDeck = ref('')
async function equipDeck(theme: (typeof decks)[number]) {
  if (preferences.cardTheme === theme || loadingDeck.value) return
  loadingDeck.value = theme
  try {
    await preloadCardImages(theme)
    preferences.cardTheme = theme
  } finally {
    loadingDeck.value = ''
  }
}
const tabs = computed(() => [
  { id: 'backs', label: t('collection.tabs.backs') },
  { id: 'tables', label: t('collection.tabs.tables') },
  { id: 'chips', label: t('collection.tabs.chips') },
  { id: 'effects', label: t('collection.tabs.effects') },
  { id: 'voices', label: t('collection.tabs.voices') },
])
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('collection.eyebrow') }}</span>
        <h1>{{ t('collection.title') }}</h1>
        <p>{{ t('collection.subtitle') }}</p>
      </div>
    </header>
    <AppTabs v-model="tab" :label="t('collection.tabsAria')" :tabs="tabs" />
    <div v-if="tab === 'backs'" class="gallery">
      <button
        v-for="deck in decks"
        :key="deck"
        type="button"
        class="gallery-item deck-item"
        :class="{ equipped: preferences.cardTheme === deck }"
        :aria-pressed="preferences.cardTheme === deck"
        :aria-busy="loadingDeck === deck"
        @click="equipDeck(deck)"
      >
        <span class="deck-fan" aria-hidden="true">
          <img :src="assets.card('A', 'S', deck)" alt="" />
          <img :src="assets.card('K', 'H', deck)" alt="" />
          <img :src="assets.card('Q', 'C', deck)" alt="" />
          <img :src="assets.card('J', 'D', deck)" alt="" />
          <img :src="assets.back(deck)" alt="" />
        </span>
        <span class="gallery-meta">
          <strong>{{ t(`cosmetics.${deck}`) }}</strong>
          <span class="gallery-state">{{
            preferences.cardTheme === deck ? t('collection.equipped') : t('collection.equip')
          }}</span>
        </span>
      </button>
    </div>
    <div v-if="tab === 'tables'" class="gallery">
      <button
        v-for="skin in tables"
        :key="skin"
        type="button"
        class="gallery-item table-item"
        :class="{ equipped: preferences.tableSkin === skin }"
        :aria-pressed="preferences.tableSkin === skin"
        @click="preferences.tableSkin = skin"
      >
        <span class="table-thumb" aria-hidden="true">
          <img :src="assets.table(skin)" alt="" />
          <img class="thumb-card" :src="assets.card('A', 'S', preferences.cardTheme)" alt="" />
          <img
            class="thumb-card second"
            :src="assets.card('K', 'H', preferences.cardTheme)"
            alt=""
          />
          <img class="thumb-chip" :src="assets.chip(25)" alt="" />
        </span>
        <span class="gallery-meta">
          <strong>{{ t(`cosmetics.${skin}`) }}</strong>
          <span class="gallery-state">{{
            preferences.tableSkin === skin ? t('collection.equipped') : t('collection.equip')
          }}</span>
        </span>
      </button>
    </div>
    <section v-if="tab === 'effects'" class="panel">
      <EmptyState
        icon="collection"
        :title="t('collection.effectsEmptyTitle')"
        :description="t('collection.effectsEmptyDescription')"
      />
    </section>
    <div v-else-if="tab !== 'backs' && tab !== 'tables'" class="collection-grid">
      <article class="panel">
        <div class="cosmetic-preview">
          <img
            v-if="tab === 'backs'"
            class="back-preview"
            :src="assets.back()"
            :alt="t('collection.cardBackAlt')"
          /><img
            v-else-if="tab === 'tables'"
            :src="assets.table()"
            :alt="t('collection.tableAlt')"
          /><img
            v-else-if="tab === 'chips'"
            class="chip-preview"
            :src="assets.chip(25)"
            :alt="t('collection.chipAlt')"
          /><AppIcon v-else name="volume" :size="58" />
        </div>
        <div class="row-between">
          <h2>
            {{
              tab === 'tables'
                ? t('collection.names.tables')
                : tab === 'voices'
                  ? t('collection.names.voices')
                  : t('collection.names.default')
            }}
          </h2>
          <AppBadge :tone="tab === 'voices' ? 'neutral' : 'success'">{{
            tab === 'voices' ? t('collection.preview') : t('collection.equipped')
          }}</AppBadge>
        </div>
        <p class="muted small">
          {{
            tab === 'voices'
              ? t('collection.voicesDescription')
              : t('collection.includedDescription')
          }}
        </p>
        <audio
          v-if="tab === 'voices'"
          controls
          preload="none"
          :src="assets.audio('voices/uk/twenty.mp3')"
          :aria-label="t('collection.voicePreviewAria')"
          style="width: 100%"
        />
      </article>
    </div>
  </main>
</template>
