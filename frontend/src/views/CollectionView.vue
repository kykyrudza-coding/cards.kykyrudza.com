<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assets } from '../config/assets'
import AppTabs from '../components/ui/AppTabs.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import AppearancePanel from '../components/settings/AppearancePanel.vue'
const { t } = useI18n()
const tab = ref('backs')
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
    <AppearancePanel v-if="tab === 'backs' || tab === 'tables'" />
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
