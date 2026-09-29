<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppTabs from '../components/ui/AppTabs.vue'
import EmptyState from '../components/ui/EmptyState.vue'
const { t } = useI18n()
const tab = ref('overview')
const tabs = computed(() => [
  { id: 'overview', label: t('statistics.tabs.overview') },
  { id: 'blackjack', label: t('statistics.tabs.blackjack') },
  { id: 'poker', label: t('statistics.tabs.poker'), disabled: true },
  { id: 'durak', label: t('statistics.tabs.durak'), disabled: true },
])
</script>
<template>
  <main class="page">
    <header class="page-heading">
      <div>
        <span class="eyebrow">{{ t('statistics.eyebrow') }}</span>
        <h1>{{ t('statistics.title') }}</h1>
        <p>{{ t('statistics.subtitle') }}</p>
      </div>
    </header>
    <AppTabs v-model="tab" :label="t('statistics.tabsAria')" :tabs="tabs" />
    <div class="metric-grid">
      <div
        v-for="label in [
          t('statistics.metrics.played'),
          t('statistics.metrics.wins'),
          t('statistics.metrics.losses'),
          t('statistics.metrics.winRate'),
        ]"
        :key="label"
        class="panel metric"
      >
        <span>{{ label }}</span
        ><strong>—</strong>
      </div>
    </div>
    <section class="panel">
      <div class="row-between">
        <h2>{{ tab === 'blackjack' ? t('statistics.performanceBlackjack') : t('statistics.performanceOverall') }}</h2>
        <span class="muted small">{{ t('statistics.bestStreak') }}</span>
      </div>
      <EmptyState
        icon="stats"
        :title="t('statistics.emptyTitle')"
        :description="t('statistics.emptyDescription')"
      />
    </section>
  </main>
</template>
